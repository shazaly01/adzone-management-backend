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
        try {
            // 1. استخراج واستجابة فورية للأحداث غير النصية بدون تسجيل Logs
            $event = $request->input('event') ?? $request->input('type') ?? '';

            $allowedEvents = [
                'onmessage',
                'onanymessage',
                'onselfmessage',
                'message',
                'unreadmessages',
            ];

            if (is_string($event) && !empty($event) && !in_array(strtolower($event), $allowedEvents, true)) {
                return response()->json([
                    'status'  => 'ignored_system_event',
                    'message' => 'System event acknowledged and ignored.'
                ], Response::HTTP_OK);
            }

            // 2. استخراج معرف الشات / المرسل بأمان لمنع أخطاء المصفوفات
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

            // 4. التحقق من وجود مرسل
            if (empty($rawSenderString)) {
                return response()->json([
                    'status'  => 'ignored_no_sender',
                    'message' => 'Webhook payload does not contain a valid sender.'
                ], Response::HTTP_OK);
            }

            // 5. تنظيف رقم المرسل بطريقة تتوافق مع WPPConnect Multi-Device
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

            if (empty($adminDigits) || empty($senderDigits)) {
                return response()->json([
                    'status'  => 'ignored_unauthorized',
                    'message' => 'Unauthorized WhatsApp sender or admin phone misconfigured.'
                ], Response::HTTP_OK);
            }

            // 7. مطابقة رقم المرسل مع رقم المدير
            if (!$this->isPhoneMatch($senderDigits, $adminDigits)) {
                return response()->json([
                    'status'  => 'ignored_unauthorized',
                    'message' => 'Unauthorized WhatsApp sender.'
                ], Response::HTTP_OK);
            }

            // 8. [الإضافة الجديدة]: فحص المستلم للرسائل الصادرة (fromMe / Note to Self)
            $fromMe = filter_var(
                $request->input('fromMe') ?? $request->input('data.fromMe') ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if ($fromMe) {
                // استخراج المستلم بأمان
                $rawRecipient = $request->input('to')
                    ?? $request->input('data.to')
                    ?? $request->input('recipient')
                    ?? '';

                if (is_array($rawRecipient)) {
                    $rawRecipient = $rawRecipient['id'] ?? $rawRecipient['remoteJid'] ?? json_encode($rawRecipient);
                }

                $rawRecipientString = is_string($rawRecipient) || is_numeric($rawRecipient) ? (string) $rawRecipient : '';
                $withoutRecipientDomain = strtok($rawRecipientString, '@');
                $phoneRecipientOnly = strtok($withoutRecipientDomain, ':');
                $recipientDigits = preg_replace('/[^0-9]/', '', (string) $phoneRecipientOnly);

                // إذا كانت الرسالة صدارة لعميل/طرف خارجي وليس لرقم المدير نفسه
                if (!empty($recipientDigits) && !$this->isPhoneMatch($recipientDigits, $adminDigits)) {
                    return response()->json([
                        'status'  => 'ignored_outbound_external',
                        'message' => 'Outbound message to external contact is ignored.'
                    ], Response::HTTP_OK);
                }
            }

            // 9. حفظ الرقم المنظف في attributes الطلب للاستخدام في الـ Controller
            $request->attributes->set('sender_phone', $senderDigits);

            return $next($request);

        } catch (\Throwable $e) {
            // تسجيل الأخطاء الحرجة فقط (Exceptions)
            Log::error('[WhatsApp Webhook Middleware Exception]', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Internal server error while validating webhook.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
