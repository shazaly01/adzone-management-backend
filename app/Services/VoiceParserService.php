<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PendingInvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class VoiceParserService
{
    /**
     * معالجة النص الخام عبر DeepSeek، مطابقة الصنف، وتخزين البيانات في جدول الاستبقاء المؤقت.
     *
     * @param string $rawText النص القادم من التدوين الصوتي أو الإدخال اليدوي للتجربة
     * @param int $userId معرف المستخدم الحالي
     * @return PendingInvoiceItem
     */
    public function parseAndStage(string $rawText, int $userId): PendingInvoiceItem
    {
        return DB::transaction(function () use ($rawText, $userId) {
            // 1. إرسال النص الفعلي إلى الـ API الخاص بـ DeepSeek واستخلاص البيانات البيانية المقننة
            $parsedData = $this->extractMetricsViaDeepSeek($rawText);

            // 2. محاولة مطابقة اسم الصنف المستخرج مرناً باستخدام الاسم أو حقل الـ aliases المصنف كـ JSON
            $matchedItem = $this->findItemByFlexMatch($parsedData['extracted_name']);

            // 3. تجهيز مخرجات الهيكل البياني الكامل لحفظه لأغراض المراجعة والـ Debugging
            $aiOutput = [
                'input_text'     => $rawText,
                'matched_search' => $parsedData['extracted_name'],
                'raw_ai_response'=> $parsedData['raw_response'],
                'metrics'        => [
                    'height'   => $parsedData['height'],
                    'width'    => $parsedData['width'],
                    'quantity' => $parsedData['quantity'],
                    'price'    => $parsedData['price'],
                ]
            ];

            // 4. إنشاء وحفظ السطر المعلق في جدول الـ Staging للمعاينة البشرية اللاحقة
            return PendingInvoiceItem::create([
                'user_id'   => $userId,
                'item_id'   => $matchedItem ? $matchedItem->id : null,
                'raw_text'  => $rawText,
                'ai_output' => $aiOutput,
                'height'    => $parsedData['height'],
                'width'     => $parsedData['width'],
                'quantity'  => $parsedData['quantity'],
                'price'     => $parsedData['price'],
            ]);
        });
    }

    /**
     * محرك البحث المرن والمطابقة الفوقية للأصناف بناءً على الاسم أو الـ Aliases (JSON)
     *
     * @param string $keyword الكلمة المستخلصة للبحث عن الصنف
     * @return Item|null
     */
    private function findItemByFlexMatch(string $keyword): ?Item
    {
        if (empty($keyword)) {
            return null;
        }

        return Item::where('is_active', true)
            ->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                      ->orWhereJsonContains('aliases', $keyword);
            })
            ->first();
    }

    /**
     * الاتصال المباشر بـ DeepSeek API وتحليل النص إلى مقاييس محددة وهيكل JSON
     *
     * @param string $text
     * @return array
     */
    private function extractMetricsViaDeepSeek(string $text): array
    {
        // الهيكل الافتراضي في حال حدوث فشل كامل في الشبكة أو الاتصال
        $fallback = [
            'extracted_name' => trim($text),
            'height'         => 0.00,
            'width'          => 0.00,
            'quantity'       => 1,
            'price'          => 0.00,
            'raw_response'   => 'Failed to communicate with DeepSeek API'
        ];

        $apiKey = config('services.deepseek.key');
        $apiUrl = config('services.deepseek.url');

        if (!$apiKey) {
            Log::error('DeepSeek API Key is missing in configuration.');
            return $fallback;
        }

        try {
            // صياغة التوجيه الصارم لضمان الحصول على رد مالي/مخزني دقيق بصيغة JSON فقط
            $systemPrompt = "You are an expert invoice parser for an advertising and digital printing system. "
                          . "Analyze the user raw input text (which can be in Arabic or English) and extract: "
                          . "1. The product or item name (extracted_name) without operational words like (اريد، اضافة، مقاس). "
                          . "2. The height (height) as float, if not mentioned default is 0. "
                          . "3. The width (width) as float, if not mentioned default is 0. "
                          . "4. The quantity (quantity) as integer, default is 1. "
                          . "5. The unit price (price) as float, default is 0. "
                          . "You MUST return ONLY a clean JSON object with keys: [extracted_name, height, width, quantity, price]. No markdown, no explanations.";

            $response = Http::withoutVerifying() // تخطي قيود شهادات الـ SSL المحلية في بيئة WAMP
                ->withToken($apiKey)
                ->timeout(10)
                ->post($apiUrl, [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $text]
                    ],
                    'response_format' => ['type' => 'json_object'], // إجبار الموديل على إرجاع كائن JSON متوافق
                    'temperature' => 0.1
                ]);

            // في حال نجاح الاتصال وتفكيك البيانات الذكية بنجاح
            if ($response->successful()) {
                $result = $response->json();
                $aiContent = $result['choices'][0]['message']['content'] ?? '';

                $data = json_decode($aiContent, true);

                if (is_array($data)) {
                    return [
                        'extracted_name' => $data['extracted_name'] ?? '',
                        'height'         => (float) ($data['height'] ?? 0.00),
                        'width'          => (float) ($data['width'] ?? 0.00),
                        'quantity'       => (int) ($data['quantity'] ?? 1),
                        'price'          => (float) ($data['price'] ?? 0.00),
                        'raw_response'   => $aiContent
                    ];
                }
            }

            // إذا رفض السيرفر الطلب لأي سبب (مثل نفاد الرصيد 402 أو غيره)، نقوم بحفظ نص الخطأ الفعلي بدقة
            Log::warning('DeepSeek responded with error code: ' . $response->status(), ['body' => $response->body()]);

            return [
                'extracted_name' => trim($text),
                'height'         => 0.00,
                'width'          => 0.00,
                'quantity'       => 1,
                'price'          => 0.00,
                'raw_response'   => 'API Error (' . $response->status() . '): ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Exception triggered during DeepSeek API call: ' . $e->getMessage());
            return $fallback;
        }
    }
}
