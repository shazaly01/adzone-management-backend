<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InventoryQueryHandler implements QueryHandlerInterface
{
    protected array $branchConnections = [
        'omd'    => 'branch_main',
        'madani' => 'branch_1',
        'port1'  => 'branch_2',
        'port2'  => 'branch_3',
    ];

    protected array $branchLabels = [
        'omd'    => 'أمدرمان',
        'madani' => 'مدني',
        'port1'  => 'بورتسودان 1',
        'port2'  => 'بورتسودان 2',
    ];

    public function getIntentName(): string
    {
        return 'inventory_report';
    }

    public function getDescription(): string
    {
        return 'عند الاستعلام عن رصيد مخزون، كمية صنف، أو توفر بضاعة معينة.';
    }

    public function handle(array $parsedIntent): string
    {
        $rawItemName = $parsedIntent['item_name'] ?? '';

        if (empty($rawItemName)) {
            return "⚠️ الرجاء تحديد اسم الصنف المراد البحث عنه (مثال: رصيد بنر 130).";
        }

        $normalizedSearch = $this->normalizeArabic($rawItemName);
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $grandTotalQty = 0.0;
        $branchResults = [];
        $foundItemName = '';

        foreach ($connectionsToQuery as $key => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $items = DB::connection($connectionName)
                    ->table('items')
                    ->join('item_stocks', 'items.id', '=', 'item_stocks.item_id')
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
                        DB::raw('SUM(item_stocks.current_quantity) as total_qty')
                    )
                    ->groupBy('items.id', 'items.name')
                    ->get();

                foreach ($items as $item) {
                    $qty = (float) $item->total_qty;
                    if ($qty > 0) {
                        $foundItemName = $item->item_name;
                        $grandTotalQty += $qty;
                        $branchResults[] = "• {$this->branchLabels[$key]}: " . number_format($qty, 1);
                    }
                }
            } catch (Throwable $e) {
                Log::error("InventoryQueryHandler Error [{$key}]: " . $e->getMessage());
            }
        }

        if (empty($branchResults)) {
            return "❌ لم يتم العثور على رصيد متاح للصنف (**{$rawItemName}**).";
        }

        $displayTitle = !empty($foundItemName) ? $foundItemName : $rawItemName;

        // رد مختصر وسريع للكمية
        $output = "📦 **رصيد ({$displayTitle}):**\n";
        $output .= implode("\n", $branchResults) . "\n";
        $output .= "-----------------------\n";
        $output .= "🔹 **الإجمالي الكلي:** " . number_format($grandTotalQty, 1);

        return $output;
    }

    protected function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        $text = preg_replace('/ى/u', 'ي', $text);
        $text = preg_replace('/ة/u', 'ه', $text);

        return trim(mb_strtolower($text));
    }
}
