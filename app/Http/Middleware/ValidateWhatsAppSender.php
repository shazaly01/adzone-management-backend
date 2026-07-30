<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // 1. تجاهل أحداث النظام غير النصية (مثل onAck, onPresenceChanged) فوراً بـ 200 OK
        $event = $request->input('event') ?? $request->input('type') ?? '';
        if (is_string($event) && !empty($event) && !in_array($event, ['onMessage', 'onAnyMessage', 'message', 'unreadmessages'])) {
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
            return response()->json([
                'status'  => 'ignored_group_message',
                'message' => 'Group messages are ignored.'
            ], Response::HTTP_OK);
        }

        // 4. التحقق من وجود مرسل (إرجاع 200 OK لتطمين WPPConnect بدون أخطاء 400)
        if (empty($rawSenderString)) {
            return response()->json([
                'status'  => 'ignored_no_sender',
                'message' => 'Webhook payload does not contain a valid sender.'
            ], Response::HTTP_OK);
        }

        // 5. تنظيف رقم المرسل
        $cleanSender = strtok($rawSenderString, '@');
        $senderDigits = preg_replace('/[^0-9]/', '', (string) $cleanSender);

        // 6. قراءة وتنظيف رقم المدير من الإعدادات
        $rawAdminPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? config('services.whatsapp.manager_phone')
            ?? '';
        $adminDigits = preg_replace('/[^0-9]/', '', (string) $rawAdminPhone);

        if (empty($adminDigits) || empty($senderDigits)) {
            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized WhatsApp sender or admin phone misconfigured.'
            ], Response::HTTP_OK);
        }

        // 7. مطابقة مرنة للرقم
        if (!$this->isPhoneMatch($senderDigits, $adminDigits)) {
            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized WhatsApp sender.'
            ], Response::HTTP_OK);
        }

        // 8. حفظ الرقم المنظف في attributes الطلب للاستخدام في الـ Controller
        $request->attributes->set('sender_phone', $senderDigits);

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
