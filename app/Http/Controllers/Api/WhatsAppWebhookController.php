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
        // 1. استخراج رقم المرسل المتحقق منه مسبقاً من الـ Middleware
        $senderPhone = $request->attributes->get('sender_phone');

        if (!$senderPhone) {
            $sender = $request->input('from')
                ?? $request->input('chatId')
                ?? $request->input('data.from')
                ?? $request->input('data.key.remoteJid')
                ?? '';

            $senderPhone = preg_replace('/[^0-9]/', '', strtok($sender, '@'));
        }

        // 2. استخراج نص الرسالة (دعم هياكل WPPConnect و Evolution)
        $messageText = $request->input('body')
            ?? $request->input('content')
            ?? $request->input('data.body')
            ?? $request->input('data.content')
            ?? $request->input('data.message.conversation')
            ?? $request->input('data.message.extendedTextMessage.text')
            ?? $request->input('message.text')
            ?? '';

        $trimmedText = trim($messageText);

        // 3. تجاهل الرسائل الفارغة
        if (empty($trimmedText)) {
            return response()->json(['status' => 'ignored_empty_message'], 200);
        }

        // 4. الحماية التامة من الحلقة التكرارية (Anti-Loop):
        // إذا كانت الرسالة تبدأ بالرمز المخفي (\u{200B})، فهذا يعني قطعاً أنها رد صادر من البوت
        if (str_starts_with($trimmedText, "\u{200B}")) {
            return response()->json(['status' => 'ignored_bot_outbound_response'], 200);
        }

        // 5. التعامل مع ميزة الإرسال الذاتي (fromMe):
        // إذا كانت الرسالة تحمل flag الصدور (fromMe = true)
        // يتم قبولها فقط إذا كانت صادرة من رقم المدير المعتمد كطلب استعلام ذاتي، وتجاهلها لو من طرف آخر
        $fromMe = $request->input('fromMe')
            ?? $request->input('data.fromMe')
            ?? $request->input('data.key.fromMe')
            ?? $request->input('key.fromMe')
            ?? false;

        if ($fromMe && !$this->isSelfMessageFromManager($senderPhone)) {
            return response()->json(['status' => 'ignored_outbound_message'], 200);
        }

        // 6. استخراج معرف الرسالة الأصلي أو توليد Hash ثابت من (الرقم + النص)
        $rawId = $request->input('id')
            ?? $request->input('data.id')
            ?? $request->input('data.key.id')
            ?? $request->input('key.id');

        $messageId = $rawId ?: md5($senderPhone . '_' . $trimmedText);

        // 7. منع معالجة الرسائل المكررة بشكل ذري (Atomic Lock)
        $cacheKey = 'wa_msg_' . $messageId;
        $isNewMessage = Cache::add($cacheKey, true, 120);

        if (!$isNewMessage) {
            return response()->json(['status' => 'ignored_duplicate'], 200);
        }

        try {
            // 8. إرسال مهمة المعالجة إلى الـ Queue الخلفي والاستجابة الفورية للسيرفر
            ProcessWhatsAppMessageJob::dispatch($senderPhone, $trimmedText, $messageId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Message queued for processing'
            ], 200);

        } catch (Throwable $e) {
            Log::error('WhatsApp Webhook Queue Dispatch Error', [
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
     * Verify if the sender is the authorized manager sending a "Note to Self".
     *
     * @param string $senderPhone
     * @return bool
     */
    protected function isSelfMessageFromManager(string $senderPhone): bool
    {
        $managerPhone = config('services.whatsapp.manager_phone');

        if (empty($managerPhone)) {
            return true;
        }

        $cleanManagerPhone = preg_replace('/[^0-9]/', '', $managerPhone);
        return $senderPhone === $cleanManagerPhone;
    }
}
