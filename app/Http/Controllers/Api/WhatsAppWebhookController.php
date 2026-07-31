<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp Webhook from WPPConnect Server / Evolution API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. استخراج رقم المرسل والمستقبل المتحقق منهما مسبقاً من الـ Middleware أو استخراجهما من الطلب
        $senderPhone    = $request->attributes->get('sender_phone');
        $recipientPhone = $request->attributes->get('recipient_phone');

        if (!$senderPhone) {
            $sender = $request->input('from')
                ?? $request->input('chatId')
                ?? $request->input('data.from')
                ?? $request->input('data.key.remoteJid')
                ?? '';

            $senderPhone = $this->cleanPhoneNumber((string) (is_array($sender) ? json_encode($sender) : $sender));
        }

        if (!$recipientPhone) {
            $recipient = $request->input('to')
                ?? $request->input('data.to')
                ?? $request->input('chatId')
                ?? $request->input('data.chatId')
                ?? '';

            $recipientPhone = $this->cleanPhoneNumber((string) (is_array($recipient) ? json_encode($recipient) : $recipient));
        }

        // 2. استخراج نص الرسالة بطريقة آمنة وبكافة الخيارات الممكنة
        $rawMessageText = $request->input('body')
            ?? $request->input('content')
            ?? $request->input('data.body')
            ?? $request->input('data.content')
            ?? $request->input('data.message.conversation')
            ?? $request->input('data.message.extendedTextMessage.text')
            ?? $request->input('message.text')
            ?? '';

        if (is_array($rawMessageText)) {
            $rawMessageText = '';
        }

        $trimmedText = trim((string) $rawMessageText);

        // 3. تجاهل الرسائل الفارغة
        if (empty($trimmedText)) {
            return response()->json(['status' => 'ignored_empty_message'], 200);
        }

        // 4. الحماية التامة من الحلقة التكرارية (Anti-Loop): تجاهل رسائل البوت الذاتية
        if (str_starts_with($trimmedText, "\u{200B}")) {
            return response()->json(['status' => 'ignored_bot_outbound_response'], 200);
        }

        // 5. التعامل مع ميزة الإرسال الذاتي (fromMe) والتحقق الإضافي المزدوج
        $rawFromMe = $request->input('fromMe')
            ?? $request->input('data.fromMe')
            ?? $request->input('data.key.fromMe')
            ?? $request->input('key.fromMe')
            ?? false;

        $fromMe = filter_var($rawFromMe, FILTER_VALIDATE_BOOLEAN);

        if ($fromMe && !$this->isSelfMessageFromManager($request, (string) $senderPhone, (string) $recipientPhone)) {
            return response()->json(['status' => 'ignored_outbound_message'], 200);
        }

        // 6. استخراج معرف الرسالة بأمان لمنع أخطاء التكرار
        $rawId = $request->input('id')
            ?? $request->input('data.id')
            ?? $request->input('data.key.id')
            ?? $request->input('key.id');

        if (is_array($rawId)) {
            $rawId = $rawId['id'] ?? $rawId['_serialized'] ?? json_encode($rawId);
        }

        $rawIdString = is_string($rawId) || is_numeric($rawId) ? (string) $rawId : '';
        $messageId   = !empty($rawIdString) ? $rawIdString : md5($senderPhone . '_' . $trimmedText);

        // 7. منع معالجة الرسائل المكررة بشكل ذري (Atomic Lock)
        $cacheKey     = 'wa_msg_' . $messageId;
        $isNewMessage = Cache::add($cacheKey, true, 120);

        if (!$isNewMessage) {
            return response()->json(['status' => 'ignored_duplicate'], 200);
        }

        try {
            // 8. إرسال مهمة المعالجة إلى الـ Queue الخلفي والاستجابة الفورية للسيرفر
            ProcessWhatsAppMessageJob::dispatch((string) $senderPhone, $trimmedText, $messageId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Message queued for processing'
            ], 200);

        } catch (Throwable $e) {
            Log::error('❌ [WA-Webhook] خطأ أثناء إرسال الـ Job إلى الـ Queue', [
                'message_id'   => $messageId,
                'sender_phone' => $senderPhone,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to dispatch message processing'
            ], 200);
        }
    }

    /**
     * Verify if the message is sent by the authorized manager to their own self.
     *
     * @param Request $request
     * @param string $senderPhone
     * @param string $recipientPhone
     * @return bool
     */
    protected function isSelfMessageFromManager(Request $request, string $senderPhone, string $recipientPhone): bool
    {
        $managerPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? '';

        if (empty($managerPhone)) {
            return true;
        }

        $cleanManagerPhone = $this->cleanPhoneNumber((string) $managerPhone);
        $isSenderManager   = $this->isPhoneMatch($senderPhone, $cleanManagerPhone);

        // فحص معاري يدعم LID (author === recipient) مع الحفاظ على الفحص القديم كبديل
        $rawAuthor    = $request->input('author') ?? $request->input('data.author') ?? '';
        $rawRecipient = $request->input('to') ?? $request->input('chatId') ?? $request->input('data.to') ?? '';

        if (is_array($rawAuthor)) {
            $rawAuthor = $rawAuthor['id'] ?? $rawAuthor['_serialized'] ?? json_encode($rawAuthor);
        }
        if (is_array($rawRecipient)) {
            $rawRecipient = $rawRecipient['id'] ?? $rawRecipient['_serialized'] ?? json_encode($rawRecipient);
        }

        $rawAuthorString    = is_string($rawAuthor) || is_numeric($rawAuthor) ? (string) $rawAuthor : '';
        $rawRecipientString = is_string($rawRecipient) || is_numeric($rawRecipient) ? (string) $rawRecipient : '';

        $isSelfByAuthor     = !empty($rawAuthorString) && !empty($rawRecipientString) && ($rawAuthorString === $rawRecipientString);
        $isRecipientManager = empty($recipientPhone) || $this->isPhoneMatch($recipientPhone, $cleanManagerPhone);

        return $isSenderManager && ($isSelfByAuthor || $isRecipientManager);
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
     * Flexible phone comparison based on trailing digits.
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
