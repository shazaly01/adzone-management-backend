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
        try {
            // 1. استخراج رقم المرسل المتحقق منه مسبقاً من الـ Middleware أو من الـ Payload
            $senderPhone = $request->attributes->get('sender_phone');

            if (!$senderPhone) {
                $sender = $request->input('from')
                    ?? $request->input('chatId')
                    ?? $request->input('data.from')
                    ?? $request->input('data.key.remoteJid')
                    ?? $request->input('sender')
                    ?? '';

                if (is_array($sender)) {
                    $sender = json_encode($sender);
                }

                $withoutDomain = strtok((string) $sender, '@');
                $phoneOnly = strtok($withoutDomain, ':');
                $senderPhone = preg_replace('/[^0-9]/', '', (string) $phoneOnly);
            }

            // 2. استخراج نص الرسالة بطريقة آمنة من المصفوفات
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

            // 3. تجاهل الرسائل الفارغة أو أحداث النظام غير النصية
            if (empty($trimmedText)) {
                return response()->json(['status' => 'ignored_empty_message'], 200);
            }

            // 4. الحماية التامة من الحلقة التكرارية (Anti-Loop): تجاهل رسائل البوت الذاتية
            if (str_starts_with($trimmedText, "\u{200B}")) {
                return response()->json(['status' => 'ignored_bot_outbound_response'], 200);
            }

            // 5. التعامل مع ميزة الإرسال الذاتي (fromMe) بحزم
            $rawFromMe = $request->input('fromMe')
                ?? $request->input('data.fromMe')
                ?? $request->input('data.key.fromMe')
                ?? $request->input('key.fromMe')
                ?? false;

            $fromMe = filter_var($rawFromMe, FILTER_VALIDATE_BOOLEAN);

            if ($fromMe) {
                // استخراج المستلم للتأكد من أنها رسالة موجهة لنفس رقم المدير (Note to Self)
                $rawRecipient = $request->input('to')
                    ?? $request->input('data.to')
                    ?? $request->input('recipient')
                    ?? '';

                if (is_array($rawRecipient)) {
                    $rawRecipient = json_encode($rawRecipient);
                }

                $withoutRecipientDomain = strtok((string) $rawRecipient, '@');
                $phoneRecipientOnly = strtok($withoutRecipientDomain, ':');
                $recipientPhone = preg_replace('/[^0-9]/', '', (string) $phoneRecipientOnly);

                if (!$this->isSelfMessageFromManager((string) $senderPhone, (string) $recipientPhone)) {
                    return response()->json(['status' => 'ignored_outbound_message'], 200);
                }
            }

            // 6. استخراج معرف الرسالة بأمان لمنع Array to string conversion
            $rawId = $request->input('id')
                ?? $request->input('data.id')
                ?? $request->input('data.key.id')
                ?? $request->input('key.id');

            if (is_array($rawId)) {
                $rawId = $rawId['id'] ?? $rawId['_serialized'] ?? json_encode($rawId);
            }

            $rawIdString = is_string($rawId) || is_numeric($rawId) ? (string) $rawId : '';
            $messageId = !empty($rawIdString) ? $rawIdString : md5($senderPhone . '_' . $trimmedText);

            // 7. منع معالجة الرسائل المكررة بشكل ذري (Atomic Lock)
            $cacheKey = 'wa_msg_' . $messageId;
            $isNewMessage = Cache::add($cacheKey, true, 120);

            if (!$isNewMessage) {
                return response()->json(['status' => 'ignored_duplicate'], 200);
            }

            // 8. إرسال مهمة المعالجة إلى الـ Queue الخلفي والاستجابة الفورية للسيرفر
            ProcessWhatsAppMessageJob::dispatch((string) $senderPhone, $trimmedText, $messageId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Message queued for processing'
            ], 200);

        } catch (Throwable $e) {
            Log::error('[WhatsApp Controller Exception]', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to dispatch message processing'
            ], 500);
        }
    }

    /**
     * Verify if the sender is the authorized manager sending a "Note to Self".
     *
     * @param string $senderPhone
     * @param string|null $recipientPhone
     * @return bool
     */
    protected function isSelfMessageFromManager(string $senderPhone, ?string $recipientPhone = null): bool
    {
        $managerPhone = config('services.wppconnect.admin_phone')
            ?? config('services.whatsapp.admin_phone')
            ?? config('services.whatsapp.manager_phone')
            ?? '';

        if (empty($managerPhone)) {
            return true;
        }

        $withoutDomain = strtok((string) $managerPhone, '@');
        $phoneOnly = strtok($withoutDomain, ':');
        $cleanManagerPhone = preg_replace('/[^0-9]/', '', (string) $phoneOnly);

        $minLen = 9;

        // 1. التحقق من أن المرسل هو المدير
        $isSenderManager = (strlen($senderPhone) >= $minLen && strlen($cleanManagerPhone) >= $minLen)
            ? (substr($senderPhone, -$minLen) === substr($cleanManagerPhone, -$minLen))
            : ($senderPhone === $cleanManagerPhone);

        if (!$isSenderManager) {
            return false;
        }

        // 2. التحقق من أن المستلم هو رقم المدير أيضاً (Note to Self)
        if (!empty($recipientPhone)) {
            $isRecipientManager = (strlen($recipientPhone) >= $minLen && strlen($cleanManagerPhone) >= $minLen)
                ? (substr($recipientPhone, -$minLen) === substr($cleanManagerPhone, -$minLen))
                : ($recipientPhone === $cleanManagerPhone);

            if (!$isRecipientManager) {
                return false;
            }
        }

        return true;
    }
}
