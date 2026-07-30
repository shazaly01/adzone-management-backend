<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppResponseService
{
    /**
     * Send a plain text message to a WhatsApp recipient via WPPConnect Server
     * with anti-ban typing simulation and dynamic human delay.
     *
     * @param string $recipientPhone
     * @param string $messageText
     * @return bool
     */
    public function sendTextMessage(string $recipientPhone, string $messageText): bool
    {
        $baseUrl = rtrim(config('services.wppconnect.base_url'), '/');
        $token   = config('services.wppconnect.token');

        if (empty($baseUrl) || empty($token)) {
            Log::error('WPPConnect configuration missing in services.php or .env.');
            return false;
        }

        // 1. إضافة الرمز المخفي (Zero-Width Space) في بداية النص لتحديد أن الرسالة صادرة من البوت (Anti-Loop)
        $formattedMessage = "\u{200B}" . $messageText;

        // 2. محاكاة السلوك البشري (إشارة جاري الكتابة + تأخير زمني)
        $this->simulateHumanTyping($baseUrl, $token, $recipientPhone, $formattedMessage);

        $endpoint = "{$baseUrl}/send-message";

        try {
            // 3. إرسال الرسالة الفعلية مع إعادة المحاولة عند التذبذب الشبكي اللحظي
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(12)
                ->retry(2, 300)
                ->post($endpoint, [
                    'phone'   => $recipientPhone,
                    'message' => $formattedMessage,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Failed sending WhatsApp message via WPPConnect Server', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'phone'  => $recipientPhone,
            ]);

        } catch (Throwable $e) {
            Log::error('Exception in WhatsAppResponseService: ' . $e->getMessage(), [
                'phone' => $recipientPhone,
            ]);
        }

        return false;
    }

    /**
     * Simulate human behavior by showing typing status and adding realistic delay.
     * Soft-fails silently to avoid preventing actual message delivery.
     *
     * @param string $baseUrl
     * @param string $token
     * @param string $recipientPhone
     * @param string $messageText
     * @return void
     */
    protected function simulateHumanTyping(string $baseUrl, string $token, string $recipientPhone, string $messageText): void
    {
        try {
            // إرسال حالة "جاري الكتابة..." لـ WPPConnect
            Http::withToken($token)
                ->acceptJson()
                ->timeout(3)
                ->post("{$baseUrl}/start-typing", [
                    'phone' => $recipientPhone,
                ]);

            // حساب وقت تأخير عشوائي ديناميكي بناءً على طول النص (بين 1.5 إلى 3.5 ثانية)
            $charCount = mb_strlen($messageText);
            $delayMicroseconds = rand(1500000, 3500000);

            // إذا كان النص طويلاً (مثل التقارير الموسعة)، نزيد التأخير لغاية 5 ثوانٍ
            if ($charCount > 300) {
                $delayMicroseconds = rand(3000000, 5000000);
            }

            usleep($delayMicroseconds);

            // إيقاف حالة الكتابة قبل إرسال النص
            Http::withToken($token)
                ->acceptJson()
                ->timeout(3)
                ->post("{$baseUrl}/stop-typing", [
                    'phone' => $recipientPhone,
                ]);

        } catch (Throwable $e) {
            // تجاوز الخطأ بهدوء في حال تعثر طلب "جاري الكتابة" لضمان تسليم التقرير
            Log::debug('WPPConnect typing simulation failed softly: ' . $e->getMessage());
        }
    }
}
