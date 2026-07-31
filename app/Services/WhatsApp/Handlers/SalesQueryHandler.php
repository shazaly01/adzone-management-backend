<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class SalesQueryHandler implements QueryHandlerInterface
{
    /**
     * ربط مفاتيح الفروع بأسماء اتصالات قاعدة البيانات
     */
    protected array $branchConnections = [
        'omd'    => 'branch_main',
        'madani' => 'branch_1',
        'port1'  => 'branch_2',
        'port2'  => 'branch_3',
    ];

    /**
     * الأسماء المترجمة للفروع للعرض على الواتساب
     */
    protected array $branchLabels = [
        'omd'    => 'المركز الرئيسي (أمدرمان)',
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
        return 'عند طلب استعلام المبيعات، الإيرادات، الكاش، أو عدد الفواتير لليوم أو تاريخ محدد أو فرع معين (مثال: مبيعات اليوم، مبيعات أمس، مبيعات فرع مدني).';
    }

    public function handle(array $parsedIntent): string
    {
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        // معالجة صريحة وآمنة للتاريخ باستخدام Carbon
        try {
            $rawDate = $parsedIntent['date'] ?? 'today';
            $date = Carbon::parse($rawDate)->format('Y-m-d');
        } catch (Throwable $e) {
            $date = now()->format('Y-m-d');
        }

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
                        COALESCE(SUM(CASE WHEN payment_type IN ('card', 'bank', 'transfer') THEN grand_total ELSE 0 END), 0) as card_amount,
                        COALESCE(SUM(CASE WHEN payment_type NOT IN ('cash', 'card', 'bank', 'transfer') THEN grand_total ELSE 0 END), 0) as credit_amount
                    ")
                    ->first();

                if ($salesData && (float) $salesData->total_amount > 0) {
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

        $formattedDisplayDate = Carbon::parse($date)->format('Y/m/d');

        // بناء ترويسة الفرع المخصص إن وُجد
        $branchHeader = "";
        if ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch])) {
            $branchHeader = "🏢 *الفرع*: {$this->branchLabels[$targetBranch]}\n";
        }

        // التوجيه الذكي في حال عدم وجود أي مبيعات في اليوم المحدد
        if ($totalInvoices === 0 && empty($branchSummaries)) {
            return $branchHeader
                 . "📊 *تقرير المبيعات بتاريخ ({$formattedDisplayDate}):*\n"
                 . "⚠️ لا توجد أي فواتير مبيعات مسجلة في هذا التاريخ.\n\n"
                 . "💡 *جرّب البحث عن:*\n"
                 . "• `مبيعات أمس`\n"
                 . "• `مبيعات اليوم في فرع مدني`\n"
                 . "• `رصيد بنر 130`";
        }

        // تنسيق الرد النهائي لشاشة الواتساب مع اسم الفرع في البداية
        $output = $branchHeader;
        $output .= "📊 *تقرير المبيعات ({$formattedDisplayDate})*:\n";
        $output .= "-----------------------------------\n";
        $output .= "💵 *الإجمالي*: *" . number_format($totalSales, 0) . " SDG* ({$totalInvoices} فاتورة)\n";
        $output .= "├ كاش: " . number_format($totalCash, 0) . " SDG\n";
        $output .= "├ بنك/شبكة: " . number_format($totalCard, 0) . " SDG\n";
        $output .= "└ آجل: " . number_format($totalCredit, 0) . " SDG\n";

        // إظهار تفاصيل الفروع فقط عند الاستعلام عن جميع الفروع وكانت متعددة
        if ($targetBranch === 'all' && count($branchSummaries) > 1) {
            $output .= "-----------------------------------\n";
            $output .= "🏢 *تفاصيل الفروع:*\n" . implode("\n", $branchSummaries);
        }

        return trim($output);
    }
}
