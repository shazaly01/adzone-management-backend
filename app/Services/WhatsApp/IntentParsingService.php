<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntentParsingService
{
    /**
     * تحليل نية النص الوارد من الواتساب وتحويله إلى كائن JSON مفسر.
     *
     * @param string $userMessage
     * @param string $phoneNumber
     * @return array|null
     */
    public function parseIntent(string $userMessage, string $phoneNumber): ?array
    {
        // 1. تنظيف وتقليم النص
        $cleanedMessage = $this->sanitizeInput($userMessage);

        if (empty($cleanedMessage)) {
            return null;
        }

        // 2. منع التكرار اللحظي (Deduplication Lock) لمدة 5 ثوانٍ
        $lockKey = 'wa_lock_' . $phoneNumber . '_' . md5($cleanedMessage);

        if (! Cache::lock($lockKey, 5)->get()) {
            Log::info("IntentParsingService: تم تجاهل الطلب المكرر اللحظي من الرقم {$phoneNumber}");
            return null;
        }

        // 3. تخزين نتيجة النية في الكاش (Caching) لمدة 10 دقائق لتوفير التوكينات
        $cacheKey = 'intent_' . md5($cleanedMessage);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($cleanedMessage) {
            return $this->executeAiInference($cleanedMessage);
        });
    }

/**
     * الاتصال المباشر بـ DeepSeek API بأسلوب Stateless
     *
     * @param string $message
     * @return array|null
     */
    protected function executeAiInference(string $message): ?array
    {
        $apiKey = config('services.deepseek.key');
        $baseUrl = config('services.deepseek.url', 'https://api.deepseek.com');

        // ضمان عدم تكرار chat/completions في الرابط
        $endpoint = str_contains($baseUrl, 'chat/completions')
            ? $baseUrl
            : rtrim($baseUrl, '/') . '/chat/completions';

        $systemPrompt = $this->buildSystemPrompt();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($endpoint, [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.1,
                'max_tokens' => 150,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                return json_decode($content, true);
            }

            Log::error('DeepSeek API Error: ' . $response->body());
            return null;

        } catch (Throwable $e) {
            Log::error('DeepSeek Connection Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * بناء الـ System Prompt المصغر والدقيق لمعالجة التواريخ والأسماء المستعارة والتطبيع اللغوي
     *
     * @return string
     */
    protected function buildSystemPrompt(): string
    {
        $today = now()->format('Y-m-d');
        $dayName = now()->locale('ar')->isoFormat('dddd');

        return <<<PROMPT
أنت محرك تحليل نيات (ERP Intent Parser). مهمتك إرجاع كائن JSON فقط يحتوي حتماً على المفاتيح: "intent", "branch", "date", "item_name".

السياق الزمني الحالي:
- تاريخ اليوم: {$today}
- اليوم هو: {$dayName}

النيات المتاحة (Intents):
- "sales_report": عند طلب المبيعات، الإيرادات، الكاش، أو عدد الفواتير.
- "inventory_report": عند الاستعلام عن رصيد مخزون، كمية صنف، أو توفر بضاعة.
- "unknown": إذا كان الطلب غير واضح أو غير مرتبط بنظام ERP.

خريطة الفروع والأسماء المستعارة (Branch Mapping):
- "omd": المركز الرئيسي, امدرمان, الفرع الرئيسي, ورشة امدرمان, omdurman
- "madani": مدني, ود مدني, فرع الجزيرة, madani
- "port1": بورتسودان 1, بورتسودان الرئيسي, فرع المريخ, المريخ, فرع الميناء, port1
- "port2": بورتسودان 2, فرع السوق, السوق, بورتسودان الفرعي, port2
- "all": الكل, جميع الفروع, كافة الفروع, الاجمالي, كل الفروع

قواعد استخراج وتحليل التواريخ (date format YYYY-MM-DD):
1. التواريخ النسبية المباشرة:
   - "اليوم" / "الليلة" / "الآن" -> {$today}
   - "أمس" / "إمبارح" / "بارح" -> احسب تاريخ يوم أمس مقارنة بـ {$today}.
   - "أول أمس" / "قبل أول إمبارح" -> احسب تاريخ قبل يومين مقارنة بـ {$today}.
2. أيام الأسبوع النسبية (بناءً على أن اليوم هو {$dayName}):
   - احسب تاريخ أقرب يوم مطالع سابق للمطلوب (مثلاً "الاثنين الماضي").
3. التواريخ الصريحة:
   - "15/5" أو "15-5" حوّلها إلى السنة الحالية "2026-05-15".
4. في تقارير المخزون (inventory_report)، اجعل قيمة date دائماً null.

قواعد استخراج اسم الصنف (item_name) والتطبيع اللغوي:
1. قم باستخراج الكلمات الأساسية فقط للصنف وتجريد النص تماماً من كلمات الزيادة مثل: (عايز، شوف لي، كم، رصيد، متوفر، عندكم، في، اسأل لي عن).
2. إزالة كافة حركات التشكيل.
3. توحيد الهمزات: تحويل (أ، إ، آ) إلى (ا).
4. توحيد الألف المقصورة والياء: تحويل (ى) إلى (ي).
5. توحيد التاء المربوطة والهاء: تحويل (ة) إلى (ه).

القيم الافتراضية:
- date: استخدم "{$today}" إذا قصد المستخدم اليوم/الان، وإلا ضعه null.
- branch: إذا لم يحدد المستخدم فرعاً، استخدم "all".

تنبيه: ارجع كائن JSON فقط بدون أي مقدمات أو شرح.
PROMPT;
    }

    /**
     * تنظيف المدخلات وتقليم المسافات والأحرف الزائدة
     *
     * @param string $text
     * @return string
     */
    protected function sanitizeInput(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim(mb_substr((string) $text, 0, 200));
    }
}
