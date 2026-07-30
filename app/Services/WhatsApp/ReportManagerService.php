<?php

namespace App\Services\WhatsApp;

use App\Models\Account;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportManagerService
{
    /**
     * Dynamic connection name for branch queries.
     */
    protected string $connectionName = 'dynamic_branch';

    /**
     * Generate report based on parsed intent.
     *
     * @param array $intentData
     * @return string
     */
    public function generateReport(array $intentData): string
    {
        $intent = $intentData['intent'] ?? 'unknown';

        if ($intent === 'unknown') {
            return "❓ لم أتمكن من فهم الطلب بدقة.\n\nيمكنك الاستفسار عن:\n- المبيعات (مثال: مبيعات اليوم، مبيعات الفرع الأول)\n- المخزون (مثال: رصيد صنف X، حد الطلب)\n- الخزينة والمالية (مثال: رصيد الصندوق، المصروفات)";
        }

        $targetBranches = $this->resolveTargetBranches($intentData['branches'] ?? ['all']);
        $period = $intentData['period'] ?? 'today';
        $dates = $this->resolveDateRange($period, $intentData['start_date'] ?? null, $intentData['end_date'] ?? null);

        $results = [];

        foreach ($targetBranches as $branchLabel => $dbName) {
            if (empty($dbName)) {
                continue;
            }

            try {
                $branchResult = $this->executeOnBranch($dbName, function () use ($intent, $dates, $intentData) {
                    return match ($intent) {
                        'sales_report'     => $this->getSalesReport($dates['start'], $dates['end']),
                        'inventory_report' => $this->getInventoryReport($intentData['item_name'] ?? null),
                        'financial_ledger' => $this->getFinancialReport($dates['start'], $dates['end']),
                        default            => null,
                    };
                });

                if ($branchResult !== null) {
                    $results[$branchLabel] = $branchResult;
                }
            } catch (Throwable $e) {
                Log::error("Failed to query branch database {$dbName}: " . $e->getMessage());
                $results[$branchLabel] = ['error' => 'تعذر الاتصال بقاعدة بيانات الفرع'];
            }
        }

        return $this->formatReportResponse($intent, $results, $dates);
    }

    /**
     * Resolve branch databases from environment variables.
     */
    protected function resolveTargetBranches(array $requestedBranches): array
    {
        $allBranches = [
            'الرئيسي'  => env('BRANCH_DB_MAIN'),
            'الفرع 1'  => env('BRANCH_DB_1'),
            'الفرع 2'  => env('BRANCH_DB_2'),
            'الفرع 3'  => env('BRANCH_DB_3'),
        ];

        if (in_array('all', $requestedBranches, true)) {
            return array_filter($allBranches);
        }

        $mapped = [];
        $branchMap = [
            'main'     => 'الرئيسي',
            'branch_1' => 'الفرع 1',
            'branch_2' => 'الفرع 2',
            'branch_3' => 'الفرع 3',
        ];

        foreach ($requestedBranches as $branchCode) {
            if (isset($branchMap[$branchCode]) && !empty($allBranches[$branchMap[$branchCode]])) {
                $label = $branchMap[$branchCode];
                $mapped[$label] = $allBranches[$label];
            }
        }

        return !empty($mapped) ? $mapped : array_filter($allBranches);
    }

    /**
     * Execute a callback query on a dynamic database connection.
     */
    protected function executeOnBranch(string $dbName, callable $callback)
    {
        Config::set("database.connections.{$this->connectionName}", array_merge(
            config('database.connections.mysql'),
            ['database' => $dbName]
        ));

        DB::purge($this->connectionName);

        return $callback();
    }

    /**
     * Query sales data using actual `sales` table structure.
     */
    protected function getSalesReport(string $startDate, string $endDate): array
    {
        $query = DB::connection($this->connectionName)
            ->table('sales')
            ->whereNull('deleted_at')
            ->where('invoice_type', 'sale') // فلترة الفواتير الصريحة واستبعاد المرتجعات
            ->whereBetween(DB::raw('DATE(invoice_date)'), [$startDate, $endDate]);

        return [
            'total_amount'   => (float) $query->sum('grand_total'),
            'invoices_count' => (int) $query->count(),
        ];
    }

    /**
     * Query inventory data using actual `item_stocks` and `items` joined structure.
     */
    protected function getInventoryReport(?string $itemName): array
    {
        $query = DB::connection($this->connectionName)
            ->table('item_stocks')
            ->join('items', 'items.id', '=', 'item_stocks.item_id')
            ->whereNull('items.deleted_at');

        if (!empty($itemName)) {
            $query->where('items.name', 'LIKE', "%{$itemName}%");
        }

        $items = $query->select(
            'items.name as item_name',
            'item_stocks.current_quantity',
            'item_stocks.reorder_level'
        )
        ->limit(10)
        ->get();

        return [
            'items' => $items->toArray(),
        ];
    }

    /**
     * Query financial balances from `accounts` & `treasuries` / `expenses`.
     */
    protected function getFinancialReport(string $startDate, string $endDate): array
    {
        // 1. جلب رصيد الخزائن من جدول الخزائن أو شجرة الحسابات (Code: 1101)
        $treasuryBalance = (float) DB::connection($this->connectionName)
            ->table('accounts')
            ->where('code', Account::CODE_TREASURY)
            ->value('current_balance') ?? 0.0;

        // 2. جلب إجمالي المصروفات من جدول المصروفات خلال الفيلتر الزمني
        $expensesTotal = (float) DB::connection($this->connectionName)
            ->table('expenses')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->sum('amount');

        return [
            'treasury_balance' => $treasuryBalance,
            'total_expenses'   => $expensesTotal,
        ];
    }

    /**
     * Resolve start and end dates based on period string.
     */
    protected function resolveDateRange(string $period, ?string $start, ?string $end): array
    {
        $today = date('Y-m-d');

        return match ($period) {
            'yesterday'  => ['start' => date('Y-m-d', strtotime('-1 day')), 'end' => date('Y-m-d', strtotime('-1 day'))],
            'this_month' => ['start' => date('Y-m-01'), 'end' => $today],
            'custom'     => ['start' => $start ?? $today, 'end' => $end ?? $today],
            default      => ['start' => $today, 'end' => $today],
        };
    }

    /**
     * Format aggregated database results into structured WhatsApp markdown.
     */
    protected function formatReportResponse(string $intent, array $results, array $dates): string
    {
        $dateText = ($dates['start'] === $dates['end']) ? $dates['start'] : "من {$dates['start']} إلى {$dates['end']}";
        $output = "";

        if ($intent === 'sales_report') {
            $output .= "📊 *تقرير المبيعات ({$dateText})*\n\n";
            $grandTotal = 0;
            $grandCount = 0;

            foreach ($results as $branch => $data) {
                if (isset($data['error'])) {
                    $output .= "🔹 *{$branch}*: ⚠️ {$data['error']}\n";
                    continue;
                }
                $amount = number_format($data['total_amount'], 2);
                $output .= "🔹 *{$branch}*: {$amount} | عدد الفواتير: {$data['invoices_count']}\n";
                $grandTotal += $data['total_amount'];
                $grandCount += $data['invoices_count'];
            }

            $formattedGrand = number_format($grandTotal, 2);
            $output .= "\n📈 *الإجمالي الكلي*: {$formattedGrand} (إجمالي الفواتير: {$grandCount})";
        } elseif ($intent === 'inventory_report') {
            $output .= "📦 *تقرير المخزون*\n\n";

            foreach ($results as $branch => $data) {
                $output .= "🏢 *{$branch}*:\n";
                if (isset($data['error'])) {
                    $output .= "  ⚠️ {$data['error']}\n";
                    continue;
                }
                if (empty($data['items'])) {
                    $output .= "  لا توجد أصناف مطابقة.\n";
                    continue;
                }
                foreach ($data['items'] as $item) {
                    $output .= "  - {$item->item_name}: الكمية الحالية ({$item->current_quantity}) | حد الطلب ({$item->reorder_level})\n";
                }
            }
        } elseif ($intent === 'financial_ledger') {
            $output .= "💰 *التقرير المالي والخزينة ({$dateText})*\n\n";

            foreach ($results as $branch => $data) {
                if (isset($data['error'])) {
                    $output .= "🔹 *{$branch}*: ⚠️ {$data['error']}\n";
                    continue;
                }
                $balance = number_format($data['treasury_balance'], 2);
                $expenses = number_format($data['total_expenses'], 2);
                $output .= "🔹 *{$branch}*:\n  • رصيد الخزينة: {$balance}\n  • إجمالي المصروفات: {$expenses}\n";
            }
        }

        return $output;
    }
}
