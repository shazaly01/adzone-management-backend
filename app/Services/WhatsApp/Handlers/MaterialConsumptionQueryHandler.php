<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class MaterialConsumptionQueryHandler implements QueryHandlerInterface
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
        'omd'    => 'أمدرمان (الرئيسي)',
        'madani' => 'مدني',
        'port1'  => 'بورتسودان 1',
        'port2'  => 'بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'material_consumption';
    }

    public function getDescription(): string
    {
        return 'استعلام عن استهلاك الخامات والمواد بالأمتار المربعة (بانر، فليكس، استيكر...) لفرع معين خلال فترة محددة.';
    }

    public function handle(array $parsedIntent): string
    {
        // تحديد الفرع المطلوب (الافتراضي: أمدرمان لضمان تركيز التقرير)
        $targetBranch = $parsedIntent['branch'] ?? 'omd';

        // في حال عدم التعرف على الفرع الممرر، نعتمد أمدرمان كفرع افتراضي
        if (!isset($this->branchConnections[$targetBranch])) {
            $targetBranch = 'omd';
        }

        $connectionName = $this->branchConnections[$targetBranch];

        if (!Config::get("database.connections.{$connectionName}")) {
            return "⚠️ تعذر الاتصال بقاعدة بيانات فرع *{$this->branchLabels[$targetBranch]}* حالياً.";
        }

        // تحديد الفترة الزمنية (اليوم / هذا الأسبوع / هذا الشهر)
        $period = $parsedIntent['period'] ?? 'this_month';
        [$fromDate, $toDate, $periodLabel] = $this->resolveDateRange($period);

        try {
            // جلب حركات الخامات ذات الأبعاد (is_dimensional = true) مطابقة لمعادلة DashboardService
            $consumedItems = SaleItem::on($connectionName)
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('items', 'sale_items.item_id', '=', 'items.id')
                ->whereNull('sales.deleted_at')
                ->whereNull('items.deleted_at')
                ->where('items.is_dimensional', true)
                ->whereBetween('sales.invoice_date', [$fromDate, $toDate])
                ->select([
                    'sale_items.item_id',
                    'items.name as item_name',
                    DB::raw("SUM(CASE WHEN sales.invoice_type = 'sale'
                        THEN (COALESCE(sale_items.length, 0) * COALESCE(sale_items.width, 0) * COALESCE(sale_items.quantity, 0))
                        ELSE -(COALESCE(sale_items.length, 0) * COALESCE(sale_items.width, 0) * COALESCE(sale_items.quantity, 0))
                        END) as consumed_meters")
                ])
                ->groupBy('sale_items.item_id', 'items.name')
                ->orderByDesc('consumed_meters')
                ->get()
                ->filter(fn($item) => (float) $item->consumed_meters > 0);

            if ($consumedItems->isEmpty()) {
                return "ℹ️ لا توجد استهلاكات خامات مسجلة لفرع *{$this->branchLabels[$targetBranch]}* خلال فترة ({$periodLabel}).";
            }

            // إجمالي الأمتار المطبوعة في هذا الفرع للفترة
            $totalBranchMeters = (float) $consumedItems->sum('consumed_meters');

            // أخذ أعلى 10 خامات استهلاكاً
            $top10Items = $consumedItems->take(10);

            return $this->formatWhatsAppOutput(
                $top10Items,
                $totalBranchMeters,
                $this->branchLabels[$targetBranch],
                $periodLabel
            );

        } catch (Throwable $e) {
            Log::error("MaterialConsumptionQueryHandler Error [{$targetBranch}]: " . $e->getMessage());
            return "❌ حدث خطأ أثناء احتساب استهلاك الخامات لفرع {$this->branchLabels[$targetBranch]}.";
        }
    }

    /**
     * تحديد نطاق التواريخ بناءً على الفترة المطلوبة
     */
    protected function resolveDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                'اليوم ' . $now->format('Y/m/d')
            ],
            'this_week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                'هذا الأسبوع'
            ],
            default => [ // 'this_month'
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay(),
                'شهر ' . $now->translatedFormat('F Y')
            ],
        };
    }

    /**
     * تنسيق تقرير استهلاك الخامات للعرض على الواتساب
     */
    protected function formatWhatsAppOutput(
        $items,
        float $totalMeters,
        string $branchName,
        string $periodLabel
    ): string {
        $output = "📉 *تقرير استهلاك الخامات بالأمتار المربعة*\n";
        $output .= "🏢 *الفرع*: {$branchName}\n";
        $output .= "📅 *الفترة*: {$periodLabel}\n";
        $output .= "-----------------------------------\n";

        foreach ($items as $index => $item) {
            $rank = $index + 1;
            $name = $item->item_name;
            $meters = (float) $item->consumed_meters;

            // حساب نسبة استهلاك الخامة من إجمالي استهلاك الفرع
            $percentage = $totalMeters > 0 ? ($meters / $totalMeters) * 100 : 0;

            $output .= "{$rank}️⃣ *{$name}*\n";
            $output .= "   ├ الاستهلاك: *" . number_format($meters, 2) . " م²*\n";
            $output .= "   └ نسبة التشغيل: 📊 *" . number_format($percentage, 1) . "%*\n\n";
        }

        $output .= "-----------------------------------\n";
        $output .= "📐 *إجمالي المساحات المطبوعة*: *" . number_format($totalMeters, 2) . " متر مربع*";

        return trim($output);
    }
}
