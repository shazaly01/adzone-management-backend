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
     * Handle incoming WhatsApp Webhook from WPPConnect Server.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('[WhatsApp Controller] Controller execution started');

        // 1. استخراج رقم المرسل المصرح له من الـ Middleware
        $senderPhone = $request->attributes->get('sender_phone');

        if (!$senderPhone) {
            $sender = $request->input('from')
                ?? $request->input('chatId')
                ?? $request->input('data.from')
                ?? '';

            $senderPhone = $this->cleanPhoneNumber((string) (is_array($sender) ? json_encode($sender) : $sender));
        }

        // 2. استخراج نص الرسالة
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

        Log::info('[WhatsApp Controller] Message Payload Extracted', [
            'sender_phone' => $senderPhone,
            'message_text' => $trimmedText,
        ]);

        // 3. تجاهل الرسائل الفارغة
        if (empty($trimmedText)) {
            Log::notice('[WhatsApp Controller] Ignored empty message text');
            return response()->json(['status' => 'ignored_empty_message'], 200);
        }

        // 4. الحماية من التكرار اللانهائي (Anti-Loop): تجاهل ردود البوت المبدوءة بـ Zero-Width Space
        if (str_starts_with($trimmedText, "\u{200B}")) {
            Log::notice('[WhatsApp Controller] Ignored bot outbound response (Anti-Loop triggered)');
            return response()->json(['status' => 'ignored_bot_outbound_response'], 200);
        }

        // 5. استخراج معرف الرسالة لمكافحة التكرار
        $rawId = $request->input('id')
            ?? $request->input('data.id')
            ?? $request->input('data.key.id')
            ?? $request->input('key.id');

        if (is_array($rawId)) {
            $rawId = $rawId['id'] ?? $rawId['_serialized'] ?? json_encode($rawId);
        }

        $rawIdString = is_string($rawId) || is_numeric($rawId) ? (string) $rawId : '';
        $messageId   = !empty($rawIdString) ? $rawIdString : md5($senderPhone . '_' . $trimmedText);

        // 6. منع تكرار المعالجة عبر Cache Lock
        $cacheKey     = 'wa_msg_' . $messageId;
        $isNewMessage = Cache::add($cacheKey, true, 120);

        if (!$isNewMessage) {
            Log::notice('[WhatsApp Controller] Ignored duplicate message payload', ['message_id' => $messageId]);
            return response()->json(['status' => 'ignored_duplicate'], 200);
        }

        try {
            // 7. تحويل الرسالة إلى الـ Queue
            ProcessWhatsAppMessageJob::dispatch((string) $senderPhone, $trimmedText, $messageId);

            Log::info('[WhatsApp Controller] Job Dispatched Successfully to Queue', [
                'sender_phone' => $senderPhone,
                'message_id'   => $messageId,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Message queued for processing'
            ], 200);

        } catch (Throwable $e) {
            Log::error('[WhatsApp Controller] Webhook Queue Dispatch Error', [
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
}
