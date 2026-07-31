<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SalesQueryHandler implements QueryHandlerInterface
{
    protected array $branchConnections = [
        'omd'    => 'branch_main',
        'madani' => 'branch_1',
        'port1'  => 'branch_2',
        'port2'  => 'branch_3',
    ];

    protected array $branchLabels = [
        'omd'    => 'المركز الرئيسي',
        'madani' => 'فرع مدني',
        'port1'  => 'فرع بورتسودان 1',
        'port2'  => 'فرع بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'sales_report';
    }

    public function getDescription(): string
    {
        return 'عند طلب استعلام المبيعات، الإيرادات، الكاش، أو عدد الفواتير لليوم أو تاريخ محدد أو فرع معين.';
    }

    public function handle(array $parsedIntent): string
    {
        $targetBranch = $parsedIntent['branch'] ?? 'all';
        $date = $parsedIntent['date'] ?? now()->format('Y-m-d');

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
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
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $salesData = DB::connection($connectionName)
                    ->table('sales')
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($date) {
                        $query->whereDate('invoice_date', $date)
                              ->orWhereDate('created_at', $date);
                    })
                    ->selectRaw("
                        COALESCE(SUM(grand_total), 0) as total_amount,
                        COUNT(id) as invoice_count,
                        COALESCE(SUM(CASE WHEN payment_type = 'cash' THEN grand_total ELSE 0 END), 0) as cash_amount,
                        COALESCE(SUM(CASE WHEN payment_type = 'card' THEN grand_total ELSE 0 END), 0) as card_amount,
                        COALESCE(SUM(CASE WHEN payment_type NOT IN ('cash', 'card') THEN grand_total ELSE 0 END), 0) as credit_amount
                    ")
                    ->first();

                if ($salesData && (float)$salesData->total_amount > 0) {
                    $branchTotal = (float) $salesData->total_amount;
                    $branchCount = (int) $salesData->invoice_count;

                    $totalSales += $branchTotal;
                    $totalInvoices += $branchCount;
                    $totalCash += (float) $salesData->cash_amount;
                    $totalCard += (float) $salesData->card_amount;
                    $totalCredit += (float) $salesData->credit_amount;

                    $branchSummaries[] = "• {$this->branchLabels[$key]}: " . number_format($branchTotal, 0) . " SDG ({$branchCount} فاتورة)";
                }
            } catch (Throwable $e) {
                Log::error("SalesQueryHandler Error [{$key}]: " . $e->getMessage());
                $branchSummaries[] = "• {$this->branchLabels[$key]}: ⚠️ غير متاح حالياً";
            }
        }

        if ($totalInvoices === 0 && empty($branchSummaries)) {
            return "📊 **مبيعات بتاريخ ({$date}):**\nلا توجد أي فواتير مبيعات مسجلة في هذا التاريخ.";
        }

        // تنسيق الرد البسيط والمختصر
        $output = "📊 **مبيعات ({$date}):**\n";
        $output .= "💵 **الإجمالي:** " . number_format($totalSales, 0) . " SDG ({$totalInvoices} فاتورة)\n";
        $output .= "💳 كاش: " . number_format($totalCash, 0) . " | شبكة: " . number_format($totalCard, 0) . " | آجل: " . number_format($totalCredit, 0) . "\n";

        if (count($branchSummaries) > 1) {
            $output .= "🏢 **تفاصيل الفروع:**\n" . implode("\n", $branchSummaries);
        }

        return $output;
    }
}
