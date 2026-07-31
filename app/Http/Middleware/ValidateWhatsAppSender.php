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
        $event = $request->input('event') ?? $request->input('type') ?? '';

        // 1. السماح بالأحداث النصية فقط وتجاهل أحداث النظام
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

        // 2. استخراج معرف المرسل (Sender)
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

        // 3. استخراج المستلم (To / Target Chat)
        $rawRecipient = $request->input('to')
            ?? $request->input('chatId')
            ?? $request->input('data.to')
            ?? $request->input('data.chatId')
            ?? '';

        if (is_array($rawRecipient)) {
            $rawRecipient = $rawRecipient['id'] ?? $rawRecipient['_serialized'] ?? json_encode($rawRecipient);
        }

        $rawRecipientString = is_string($rawRecipient) || is_numeric($rawRecipient) ? (string) $rawRecipient : '';

        // 4. استخراج الكاتب الأصلي (Author)
        $rawAuthor = $request->input('author')
            ?? $request->input('data.author')
            ?? '';

        if (is_array($rawAuthor)) {
            $rawAuthor = $rawAuthor['id'] ?? $rawAuthor['_serialized'] ?? json_encode($rawAuthor);
        }

        $rawAuthorString = is_string($rawAuthor) || is_numeric($rawAuthor) ? (string) $rawAuthor : '';

        // 5. استبعاد المجموعات فوراً
        $isGroup = $request->input('isGroupMsg')
            ?? $request->input('isGroup')
            ?? $request->input('data.isGroup')
            ?? (!empty($rawSenderString) && str_contains($rawSenderString, '@g.us'))
            ?? (!empty($rawRecipientString) && str_contains($rawRecipientString, '@g.us'));

        if (filter_var($isGroup, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'status'  => 'ignored_group_message',
                'message' => 'Group messages are ignored.'
            ], Response::HTTP_OK);
        }

        // 6. التحقق من تنظيف رقم المدير والـ Sender
        $senderDigits = $this->cleanPhoneNumber($rawSenderString);
        $rawAdminPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? config('services.whatsapp.manager_phone')
            ?? '';

        $adminDigits = $this->cleanPhoneNumber((string) $rawAdminPhone);

        if (empty($adminDigits) || empty($senderDigits)) {
            Log::error('[WhatsApp Webhook] Admin config missing or invalid sender digits');

            return response()->json([
                'status'  => 'ignored_unauthorized',
                'message' => 'Unauthorized sender or missing admin config.'
            ], Response::HTTP_OK);
        }

        // 7. الشرط الأول: المرسل يجب أن يكون هو المدير المصرح له
        if (!$this->isPhoneMatch($senderDigits, $adminDigits)) {
            return response()->json([
                'status'  => 'ignored_unauthorized_sender',
                'message' => 'Sender is not authorized admin.'
            ], Response::HTTP_OK);
        }

        // 8. الشرط المعماري الثاني (الرسالة الذاتية Note to Self):
        // إما أن يكون الكاتب مساوياً للمستلم تماماً (author === to)
        // أو أن يكون المستلم المباشر هو رقم المدير بـ @c.us
        $isSelfChatByAuthor = !empty($rawAuthorString) && !empty($rawRecipientString) && ($rawAuthorString === $rawRecipientString);

        $recipientDigits    = $this->cleanPhoneNumber($rawRecipientString);
        $isSelfChatByPhone  = !empty($recipientDigits) && $this->isPhoneMatch($recipientDigits, $adminDigits);

        $isSelfChat = $isSelfChatByAuthor || $isSelfChatByPhone;

        if (!$isSelfChat) {
            return response()->json([
                'status'  => 'ignored_not_self_chat',
                'message' => 'Outbound message directed to external contact ignored.'
            ], Response::HTTP_OK);
        }

        // 9. اعتماد الطلب وتمريره
        $request->attributes->set('sender_phone', $senderDigits);

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
     * Compare two phone numbers flexibly by checking trailing digits.
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
