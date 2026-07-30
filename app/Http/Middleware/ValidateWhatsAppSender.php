<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWhatsAppSender
{
    /**
     * Handle an incoming WhatsApp Webhook request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 0. تسجيل الاستقبال الأولي للـ Webhook
        $event = $request->input('event') ?? $request->input('type') ?? '';

        Log::info('[WhatsApp Webhook] Incoming Webhook request received', [
            'event'       => $event,
            'ip'          => $request->ip(),
            'all_payload' => $request->all(),
        ]);

        // 1. السماح بالأحداث النصية (بما فيها onSelfMessage) وتجاهل أحداث النظام غير النصية
        $allowedEvents = [
            'onmessage',
            'onanymessage',
            'onselfmessage',
            'message',
            'unreadmessages',
        ];

        if (is_string($event) && !empty($event) && !in_array(strtolower($event), $allowedEvents, true)) {
            Log::notice('[WhatsApp Webhook] Ignored system event', ['event' => $event]);

            return response()->json([
                'status'  => 'ignored_system_event',
                'message' => 'System event acknowledged and ignored.'
            ], Response::HTTP_OK);
        }

        // 2. استخراج معرف الشات الأصلي بأمان لمنع أخطاء المصفوفات
        $rawSender = $request->input('from')
            ?? $request->input('chatId')
            ?? $request->input('sender.id')
            ?? $request->input('data.from')
            ?? $request->input('data.key.remoteJid')
            ?? $request->input('sender')
            ?? '';

        if (is_array($rawSender)) {
            $rawSender = $rawSender['id'] ?? $rawSender['remoteJid'] ?? json_encode($rawSender);
        }

        $rawSenderString = is_string($rawSender) || is_numeric($rawSender) ? (string) $rawSender : '';

        // 3. تجاهل رسائل المجموعات فوراً
        $isGroup = $request->input('isGroupMsg')
            ?? $request->input('isGroup')
            ?? $request->input('data.isGroup')
            ?? (!empty($rawSenderString) && str_contains($rawSenderString, '@g.us'));

        if (filter_var($isGroup, FILTER_VALIDATE_BOOLEAN)) {
            Log::notice('[WhatsApp Webhook] Ignored group message', ['sender' => $rawSenderString]);

            return response()->json([
                'status'  => 'ignored_group_message',
                'message' => 'Group messages are ignored.'
            ], Response::HTTP_OK);
        }

        // 4. التحقق من وجود مرسل
        if (empty($rawSenderString)) {
            Log::warning('[WhatsApp Webhook] No valid sender found in payload');

            return response()->json([
                'status'  => 'ignored_no_sender',
                'message' => 'Webhook payload does not contain a valid sender.'
            ], Response::HTTP_OK);
        }

        // 5. تنظيف رقم المرسل بطريقة آمنة تتوافق مع نظام WPPConnect Multi-Device (إزالة @ ورمز الجهاز :xx)
        $withoutDomain = strtok($rawSenderString, '@');
        $phoneOnly = strtok($withoutDomain, ':');
        $senderDigits = preg_replace('/[^0-9]/', '', (string) $phoneOnly);

        // 6. قراءة وتنظيف رقم المدير من الإعدادات
        $rawAdminPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? config('services.whatsapp.manager_phone')
            ?? '';

        $withoutAdminDomain = strtok((string) $rawAdminPhone, '@');
        $phoneAdminOnly = strtok($withoutAdminDomain, ':');
        $adminDigits = preg_replace('/[^0-9]/', '', (string) $phoneAdminOnly);

        Log::info('[WhatsApp Webhook] Phone Parsing Verification', [
            'raw_sender_string' => $rawSenderString,
            'parsed_sender'     => $senderDigits,
            'configured_admin'  => $adminDigits,
        ]);

        if (empty($adminDigits) || empty($senderDigits)) {
            Log::error('[WhatsApp Webhook] Missing sender digits or admin configuration missing');

            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized WhatsApp sender or admin phone misconfigured.'
            ], Response::HTTP_OK);
        }

        // 7. مطابقة مرنة للرقم
        if (!$this->isPhoneMatch($senderDigits, $adminDigits)) {
            Log::warning('[WhatsApp Webhook] Sender phone does not match admin phone', [
                'sender' => $senderDigits,
                'admin'  => $adminDigits,
            ]);

            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized WhatsApp sender.'
            ], Response::HTTP_OK);
        }

        // 8. حفظ الرقم المنظف في attributes الطلب للاستخدام في الـ Controller
        $request->attributes->set('sender_phone', $senderDigits);

        Log::info('[WhatsApp Webhook] Middleware passed successfully', ['sender_phone' => $senderDigits]);

        return $next($request);
    }

    /**
     * Compare two phone numbers flexibly by checking the trailing digits.
     *
     * @param string $phone1
     * @param string $phone2
     * @return bool
     */
    protected function isPhoneMatch(string $phone1, string $phone2): bool
    {
        if ($phone1 === $phone2) {
            return true;
        }

        $minLen = 9;
        if (strlen($phone1) >= $minLen && strlen($phone2) >= $minLen) {
            return substr($phone1, -$minLen) === substr($phone2, -$minLen);
        }

        return false;
    }
}
