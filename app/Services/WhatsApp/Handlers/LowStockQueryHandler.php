<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Item;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class LowStockQueryHandler implements QueryHandlerInterface
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
        'omd'    => 'أمدرمان',
        'madani' => 'مدني',
        'port1'  => 'بورتسودان 1',
        'port2'  => 'بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'low_stock';
    }

    public function getDescription(): string
    {
        return 'استعلام عن النواقص وأعلى 10 أصناف وخامات منخفضة وصلت للحد الأدنى للمخزون أو قاربت على النفاد.';
    }

    public function handle(array $parsedIntent): string
    {
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $lowStockItems = [];

        foreach ($connectionsToQuery as $branchKey => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                // جلب الأصناف مع وحدتها الأساسية وأرصدة المخازن
                $items = Item::on($connectionName)
                    ->with(['baseUnit', 'stocks'])
                    ->where('item_type', 'product') // استبعاد الخدمات (لا تجرد)
                    ->whereNull('deleted_at')
                    ->get()
                    ->filter(function ($item) {
                        $currentQty = (float) $item->stocks->sum('current_quantity');
                        // جمع حد الطلب من المخازن المرتبطة بالفرع
                        $reorderLevel = (float) $item->stocks->sum('reorder_level');

                        return $reorderLevel > 0 ? $currentQty <= $reorderLevel : $currentQty <= 5;
                    });

                foreach ($items as $item) {
                    $currentQty = (float) $item->stocks->sum('current_quantity');
                    $reorderLevel = (float) $item->stocks->sum('reorder_level');
                    $mainUnit = $item->baseUnit->name ?? 'وحدة';

                    $lowStockItems[] = [
                        'name'          => $item->name,
                        'qty'           => $currentQty,
                        'reorder_level' => $reorderLevel,
                        'unit'          => $mainUnit,
                        'branch'        => $this->branchLabels[$branchKey] ?? $branchKey,
                    ];
                }
            } catch (Throwable $e) {
                Log::error("LowStockQueryHandler Error [{$branchKey}]: " . $e->getMessage());
            }
        }

        if (empty($lowStockItems)) {
            return "✅ *ممتاز!* لا توجد أي أصناف أو خامات وصلت للحد الأدنى للمخزون حالياً.";
        }

        // الفرز تصاعدياً لتصدر الأصناف الأقل رصيداً القائمة
        usort($lowStockItems, fn($a, $b) => $a['qty'] <=> $b['qty']);
        $top10LowStock = array_slice($lowStockItems, 0, 10);

        return $this->formatWhatsAppOutput($top10LowStock, $targetBranch);
    }

    protected function formatWhatsAppOutput(array $items, string $targetBranch): string
    {
        $branchTitle = ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch]))
            ? "({$this->branchLabels[$targetBranch]})"
            : "(جميع الفروع)";

        $output = "⚠️ *تنبيه النواقص والأصناف الحرجة {$branchTitle}*\n";
        $output .= "-----------------------------------\n";

        foreach ($items as $index => $item) {
            $rank = $index + 1;
            $name = $item['name'];
            $qty = number_format($item['qty'], 2);
            $reorderLevel = $item['reorder_level'] > 0 ? number_format($item['reorder_level'], 2) : 'غير محدد';
            $unit = $item['unit'];
            $branch = $item['branch'];

            $output .= "{$rank}️⃣ *{$name}*\n";
            $output .= "   ├ المتبقي: *{$qty} {$unit}*\n";
            if ($item['reorder_level'] > 0) {
                $output .= "   ├ حد الأمان: {$reorderLevel} {$unit}\n";
            }
            $output .= "   └ الفرع: {$branch}\n\n";
        }

        $output .= "-----------------------------------\n";
        $output .= "💡 *توصية*: يرجى إصدار أذونات الشراء لتفادي توقف العمل.";

        return trim($output);
    }
}
