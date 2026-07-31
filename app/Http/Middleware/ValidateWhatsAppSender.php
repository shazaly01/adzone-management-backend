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

        // 2. استخراج معرف المرسل الأصلي بأمان
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

        // 3. استخراج معرف المستقبل الأصلي (الشات Target / To) بأمان
        $rawRecipient = $request->input('to')
            ?? $request->input('data.to')
            ?? $request->input('chatId')
            ?? $request->input('data.chatId')
            ?? $request->input('data.key.remoteJid')
            ?? '';

        if (is_array($rawRecipient)) {
            $rawRecipient = $rawRecipient['id'] ?? $rawRecipient['remoteJid'] ?? json_encode($rawRecipient);
        }

        $rawRecipientString = is_string($rawRecipient) || is_numeric($rawRecipient) ? (string) $rawRecipient : '';

        // 4. تجاهل رسائل المجموعات فوراً
        $isGroup = $request->input('isGroupMsg')
            ?? $request->input('isGroup')
            ?? $request->input('data.isGroup')
            ?? (!empty($rawSenderString) && str_contains($rawSenderString, '@g.us'))
            ?? (!empty($rawRecipientString) && str_contains($rawRecipientString, '@g.us'));

        if (filter_var($isGroup, FILTER_VALIDATE_BOOLEAN)) {
            Log::notice('[WhatsApp Webhook] Ignored group message', [
                'sender'    => $rawSenderString,
                'recipient' => $rawRecipientString,
            ]);

            return response()->json([
                'status'  => 'ignored_group_message',
                'message' => 'Group messages are ignored.'
            ], Response::HTTP_OK);
        }

        // 5. التحقق من وجود مرسل
        if (empty($rawSenderString)) {
            Log::warning('[WhatsApp Webhook] No valid sender found in payload');

            return response()->json([
                'status'  => 'ignored_no_sender',
                'message' => 'Webhook payload does not contain a valid sender.'
            ], Response::HTTP_OK);
        }

        // 6. تنظيف وتجريد رقم المرسل ورقم المستقبل
        $senderDigits    = $this->cleanPhoneNumber($rawSenderString);
        $recipientDigits = $this->cleanPhoneNumber($rawRecipientString);

        // 7. قراءة وتنظيف رقم المدير المعتمد من التكوين
        $rawAdminPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? config('services.whatsapp.manager_phone')
            ?? '';

        $adminDigits = $this->cleanPhoneNumber((string) $rawAdminPhone);

        Log::info('[WhatsApp Webhook] Phone Parsing & Validation Details', [
            'raw_sender'       => $rawSenderString,
            'parsed_sender'    => $senderDigits,
            'raw_recipient'    => $rawRecipientString,
            'parsed_recipient' => $recipientDigits,
            'configured_admin' => $adminDigits,
        ]);

        if (empty($adminDigits) || empty($senderDigits)) {
            Log::error('[WhatsApp Webhook] Missing sender digits or admin configuration missing', [
                'sender_digits' => $senderDigits,
                'admin_digits'  => $adminDigits,
            ]);

            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized WhatsApp sender or admin phone misconfigured.'
            ], Response::HTTP_OK);
        }

        // 8. التحقق الأول: يجب أن يكون المرسل هو رقم المدير
        $isSenderAdmin = $this->isPhoneMatch($senderDigits, $adminDigits);
        if (!$isSenderAdmin) {
            Log::warning('[WhatsApp Webhook] Sender phone does not match admin phone', [
                'sender' => $senderDigits,
                'admin'  => $adminDigits,
            ]);

            return response()->json([
                'status'  => 'ignored_unauthorized_sender',
                'message' => 'Sender is not authorized admin.'
            ], Response::HTTP_OK);
        }

        // 9. التحقق الثاني (الشرط الجوهري): يجب أن تكون الرسالة موجهة لرقم المدير نفسه (Self-Chat / Note to Self)
        if (!empty($recipientDigits)) {
            $isRecipientAdmin = $this->isPhoneMatch($recipientDigits, $adminDigits);
            if (!$isRecipientAdmin) {
                Log::notice('[WhatsApp Webhook] Outbound message ignored - Not sent to self', [
                    'sender'    => $senderDigits,
                    'recipient' => $recipientDigits,
                    'admin'     => $adminDigits,
                ]);

                return response()->json([
                    'status'  => 'ignored_not_self_chat',
                    'message' => 'Message is directed to another contact, ignored.'
                ], Response::HTTP_OK);
            }
        }

        // 10. حفظ الأرقام المنظفة في attributes الطلب للاستخدام في الـ Controller
        $request->attributes->set('sender_phone', $senderDigits);
        $request->attributes->set('recipient_phone', $recipientDigits);

        Log::info('[WhatsApp Webhook] Self-Chat Verification Passed Successfully', [
            'sender_phone'    => $senderDigits,
            'recipient_phone' => $recipientDigits,
        ]);

        return $next($request);
    }

    /**
     * Clean phone number string by removing domain suffix, device ports, and non-numeric characters.
     *
     * @param string $rawPhone
     * @return string
     */
    protected function cleanPhoneNumber(string $rawPhone): string
    {
        if (empty($rawPhone)) {
            return '';
        }

        $withoutDomain = strtok($rawPhone, '@');
        $phoneOnly      = strtok($withoutDomain, ':');

        return preg_replace('/[^0-9]/', '', (string) $phoneOnly);
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
