<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportManagerService
{
    /**
     * خريطة الربط بين المفاتيح المعيارية للفروع وأسماء الاتصالات في config/database.php
     */
    protected array $branchConnections = [
        'omd'    => 'branch_main',
        'madani' => 'branch_1',
        'port1'  => 'branch_2',
        'port2'  => 'branch_3',
    ];

    /**
     * الأسماء المترجمة للفروع للعرض في تقارير الواتساب
     */
    protected array $branchLabels = [
        'omd'    => 'المركز الرئيسي (أمدرمان)',
        'madani' => 'فرع مدني',
        'port1'  => 'فرع بورتسودان 1',
        'port2'  => 'فرع بورتسودان 2',
    ];

    /**
     * معالجة النية وإصدار التقرير المناسب بناءً على بيانات الـ JSON
     *
     * @param array $parsedIntent
     * @return string
     */
    public function generateReport(array $parsedIntent): string
    {
        $intent = $parsedIntent['intent'] ?? 'unknown';

        switch ($intent) {
            case 'sales_report':
                return $this->getSalesReport($parsedIntent);

            case 'inventory_report':
                return $this->getInventoryReport($parsedIntent);

            default:
                return "عذراً، لم أستطع فهم نوع التقرير المطلوب بدقة. يمكنك استعلام المبيعات أو رصيد المخزون بصياغات مثل:\n- مبيعات اليوم لجميع الفروع\n- مبيعات فرع مدني أمس\n- رصيد بنر 130 في بورتسودان 1";
        }
    }

    /**
     * إنتاج تقرير المبيعات للفروع المحددة أو كافة الفروع
     *
     * @param array $data
     * @return string
     */
    protected function getSalesReport(array $data): string
    {
        $targetBranch = $data['branch'] ?? 'all';
        $date = $data['date'] ?? now()->format('Y-m-d');

        $connectionsToQuery = [];

        if ($targetBranch === 'all' || ! isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $totalSales = 0.0;
        $totalInvoices = 0;
        $totalCash = 0.0;
        $totalCard = 0.0;
        $totalCredit = 0.0;
        $branchSummaries = [];

        foreach ($connectionsToQuery as $key => $connectionName) {
            if (! Config::get("database.connections.{$connectionName}")) {
                Log::warning("ReportManagerService: الاتصال {$connectionName} غير معرف في كود الإعدادات.");
                continue;
            }

            try {
                $salesData = DB::connection($connectionName)
                    ->table('invoices')
                    ->whereDate('created_at', $date)
                    ->whereNull('deleted_at')
                    ->selectRaw('
                        COALESCE(SUM(grand_total), 0) as total_amount,
                        COUNT(id) as invoice_count,
                        COALESCE(SUM(paid_cash), 0) as cash_amount,
                        COALESCE(SUM(paid_card), 0) as card_amount,
                        COALESCE(SUM(due_amount), 0) as credit_amount
                    ')
                    ->first();

                if ($salesData) {
                    $branchTotal = (float) $salesData->total_amount;
                    $branchCount = (int) $salesData->invoice_count;

                    $totalSales += $branchTotal;
                    $totalInvoices += $branchCount;
                    $totalCash += (float) $salesData->cash_amount;
                    $totalCard += (float) $salesData->card_amount;
                    $totalCredit += (float) $salesData->credit_amount;

                    $branchSummaries[] = "• **" . $this->branchLabels[$key] . ":** " . number_format($branchTotal, 2) . " SDG (" . $branchCount . " فاتورة)";
                }
            } catch (Throwable $e) {
                Log::error("ReportManagerService: فشل الاستعلام من الفرع {$key} على الاتصال {$connectionName}: " . $e->getMessage());
                $branchSummaries[] = "• **" . $this->branchLabels[$key] . ":** ⚠️ يتعذر الاتصال حالياً";
            }
        }

        $formattedTotalSales = number_format($totalSales, 2);
        $formattedCash = number_format($totalCash, 2);
        $formattedCard = number_format($totalCard, 2);
        $formattedCredit = number_format($totalCredit, 2);

        $responseMessage = "📊 **تقرير المبيعات اليومي**\n";
        $responseMessage .= "📅 **التاريخ:** {$date}\n";
        $responseMessage .= "-----------------------------------\n";
        $responseMessage .= "💵 **إجمالي المبيعات:** {$formattedTotalSales} SDG\n";
        $responseMessage .= "🧾 **عدد الفواتير:** {$totalInvoices}\n\n";
        $responseMessage .= "💳 **تفصيل طرق الدفع:**\n";
        $responseMessage .= "• نقداً (Cash): {$formattedCash} SDG\n";
        $responseMessage .= "• بنك / شبكة (Card): {$formattedCard} SDG\n";
        $responseMessage .= "• آجل (Credit): {$formattedCredit} SDG\n";
        $responseMessage .= "-----------------------------------\n";
        $responseMessage .= "🏢 **تفاصيل الفروع:**\n";
        $responseMessage .= implode("\n", $branchSummaries);

        return $responseMessage;
    }

    /**
     * إنتاج تقرير المخزون مع تطبيق البحث الضبابي والتطبيع اللغوي العربي
     *
     * @param array $data
     * @return string
     */
    protected function getInventoryReport(array $data): string
    {
        $rawItemName = $data['item_name'] ?? '';

        if (empty($rawItemName)) {
            return "⚠️ الرجاء تحديد اسم الصنف المراد الاستعلام عنه (مثال: رصيد بنر 130).";
        }

        $normalizedSearch = $this->normalizeArabic($rawItemName);

        $targetBranch = $data['branch'] ?? 'all';
        $connectionsToQuery = [];

        if ($targetBranch === 'all' || ! isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $resultsFound = false;
        $responseMessage = "📦 **تقرير مخزون الأصناف**\n";
        $responseMessage .= "🔍 البحث عن: **{$rawItemName}**\n";
        $responseMessage .= "-----------------------------------\n";

        foreach ($connectionsToQuery as $key => $connectionName) {
            if (! Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                // تطبيق التطبيع على مستوى الاستعلام لمنع تعارض الهمزات والياءات
                $items = DB::connection($connectionName)
                    ->table('items')
                    ->join('item_stocks', 'items.id', '=', 'item_stocks.item_id')
                    ->join('stores', 'item_stocks.store_id', '=', 'stores.id')
                    ->whereNull('items.deleted_at')
                    ->whereRaw("
                        LOWER(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(items.name, 'أ', 'ا'),
                                        'إ', 'ا'),
                                    'آ', 'ا'),
                                'ى', 'ي'),
                            'ة', 'ه')
                        ) LIKE ?", ["%{$normalizedSearch}%"])
                    ->select(
                        'items.name as item_name',
                        'items.code as item_code',
                        'stores.name as store_name',
                        'item_stocks.quantity as quantity'
                    )
                    ->get();

                if ($items->isNotEmpty()) {
                    $resultsFound = true;
                    $responseMessage .= "🏢 **{$this->branchLabels[$key]}**\n";

                    $groupedItems = $items->groupBy('item_code');

                    foreach ($groupedItems as $code => $stocks) {
                        $itemName = $stocks->first()->item_name;
                        $totalQty = $stocks->sum('quantity');

                        $responseMessage .= "🔹 **{$itemName}** (كود: {$code})\n";
                        $responseMessage .= "   إجمالي الرصيد: **" . number_format($totalQty, 2) . "**\n";

                        foreach ($stocks as $stock) {
                            $responseMessage .= "   └ {$stock->store_name}: " . number_format($stock->quantity, 2) . "\n";
                        }
                    }
                    $responseMessage .= "-----------------------------------\n";
                }
            } catch (Throwable $e) {
                Log::error("ReportManagerService: فشل استعلام المخزون في الفرع {$key}: " . $e->getMessage());
            }
        }

        if (! $resultsFound) {
            return "❌ لم يتم العثور على أي نتائج تطابق الصنف (**{$rawItemName}**) في الفروع المحددة.";
        }

        return $responseMessage;
    }

    /**
     * تطبيع النص العربي لتسهيل البحث (إزالة التشكيل، توحيد الهمزات، الياء، والتاء المربوطة)
     *
     * @param string $text
     * @return string
     */
    protected function normalizeArabic(string $text): string
    {
        // 1. إزالة التشكيل بالحركات (فتحة، ضمة، كسرة، تنوين، شدة، سكون)
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);

        // 2. توحيد أشكال الهمزات إلى ألف مجردة (أ، إ، آ -> ا)
        $text = preg_replace('/[أإآ]/u', 'ا', $text);

        // 3. توحيد الألف المقصورة والياء في نهاية الكلمة (ى -> ي)
        $text = preg_replace('/ى/u', 'ي', $text);

        // 4. توحيد التاء المربوطة والهاء في نهاية الكلمة (ة -> ه)
        $text = preg_replace('/ة/u', 'ه', $text);

        return trim(mb_strtolower($text));
    }
}
