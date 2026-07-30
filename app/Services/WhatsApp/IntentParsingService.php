<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntentParsingService
{
    /**
     * Parse user natural language text into a structured intent array using DeepSeek.
     *
     * @param string $message
     * @return array
     */
    public function parse(string $message): array
    {
        $apiKey = config('services.deepseek.key');
        $endpoint = config('services.deepseek.url', 'https://api.deepseek.com/chat/completions');

        if (empty($apiKey)) {
            Log::warning('DeepSeek API key is not configured.');
            return $this->fallbackIntent($message);
        }

        // استخراج تاريخ اليوم الحالي واسم اليوم ديناميكياً لإرفاقه في الـ Prompt
        $currentDate = now()->format('Y-m-d');
        $currentDayName = now()->format('l');

        $systemPrompt = <<<PROMPT
You are a database query intent parser for a retail and accounting system.
Current System Date: {$currentDate} ({$currentDayName}).
Use this current date reference to evaluate relative terms like "today", "yesterday", "this week", or "this month".

Analyze the user request in Arabic and extract the intent, target branches, time period, and search keywords.
Return ONLY valid JSON without any markdown block syntax or surrounding text.

Intents available:
- "sales_report": Total sales, revenue, or invoice counts.
- "inventory_report": Stock levels, product availability, or low-stock items.
- "financial_ledger": Treasury balances, bank accounts, or total expenses.
- "unknown": Unrelated queries or non-system requests.

Branches available:
- "main", "branch_1", "branch_2", "branch_3", "all".

JSON Format required:
{
    "intent": "sales_report" | "inventory_report" | "financial_ledger" | "unknown",
    "branches": ["main", "branch_1"],
    "period": "today" | "yesterday" | "this_month" | "custom",
    "start_date": "YYYY-MM-DD" or null,
    "end_date": "YYYY-MM-DD" or null,
    "item_name": "string" or null
}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(12)->post($endpoint, [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role'    => 'user',
                        'content' => $message
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.1
            ]);

            if ($response->successful()) {
                $rawText = trim($response->json('choices.0.message.content') ?? '');

                // تنظيف رد الـ JSON من أية علامات تنصيص Markdown محتملة
                $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawText);
                $parsed = json_decode($cleanJson, true);

                if (is_array($parsed) && isset($parsed['intent'])) {
                    return $this->sanitizeIntentSchema($parsed);
                }
            }

            Log::warning('DeepSeek failed to return structured intent', [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);

        } catch (Throwable $e) {
            Log::error('Exception in IntentParsingService (DeepSeek): ' . $e->getMessage());
        }

        return $this->fallbackIntent($message);
    }

    /**
     * Ensure all required keys exist in the parsed intent array with proper types.
     *
     * @param array $parsed
     * @return array
     */
    protected function sanitizeIntentSchema(array $parsed): array
    {
        return [
            'intent'     => $parsed['intent'] ?? 'unknown',
            'branches'   => is_array($parsed['branches'] ?? null) ? $parsed['branches'] : ['all'],
            'period'     => $parsed['period'] ?? 'today',
            'start_date' => $parsed['start_date'] ?? date('Y-m-d'),
            'end_date'   => $parsed['end_date'] ?? date('Y-m-d'),
            'item_name'  => $parsed['item_name'] ?? null,
        ];
    }

    /**
     * Provide a standard fallback structure if parsing fails.
     *
     * @param string $message
     * @return array
     */
    protected function fallbackIntent(string $message): array
    {
        $today = date('Y-m-d');
        return [
            'intent'     => 'unknown',
            'branches'   => ['all'],
            'period'     => 'today',
            'start_date' => $today,
            'end_date'   => $today,
            'item_name'  => null,
            'raw_text'   => $message
        ];
    }
}
