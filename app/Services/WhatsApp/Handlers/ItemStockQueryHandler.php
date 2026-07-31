<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Item;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Throwable;

class ItemStockQueryHandler implements QueryHandlerInterface
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
        return 'item_stock';
    }

    public function getDescription(): string
    {
        return 'استعلام عن رصيد الكميات المتوفرة من صنف معين أو عدة أصناف (مثل: رصيد بنر 130، جرد ورق 70 جرام، رول اب مدني)';
    }

    public function handle(array $parsedIntent): string
    {
        $search = trim($parsedIntent['item_name'] ?? $parsedIntent['search'] ?? '');
        $targetBranch = $parsedIntent['branch'] ?? 'all';

        if (empty($search)) {
            return "⚠️ يرجى تحديد اسم الصنف أو الباركود للاستعلام عن المخزون.";
        }

        // تحديد الفروع المراد الاستعلام عنها
        $connectionsToQuery = [];
        if ($targetBranch === 'all' || !isset($this->branchConnections[$targetBranch])) {
            $connectionsToQuery = $this->branchConnections;
        } else {
            $connectionsToQuery[$targetBranch] = $this->branchConnections[$targetBranch];
        }

        $normalizedSearch = $this->normalizeArabic($search);
        $results = collect();

        foreach ($connectionsToQuery as $branchKey => $connectionName) {
            if (!Config::get("database.connections.{$connectionName}")) {
                continue;
            }

            try {
                $branchResults = $this->searchInBranch($connectionName, $search, $normalizedSearch);
                if (!empty($branchResults['items']) && $branchResults['items']->isNotEmpty()) {
                    $results->put($branchKey, $branchResults);
                }
            } catch (Throwable $e) {
                Log::error("ItemStockQueryHandler Error [{$branchKey}]: " . $e->getMessage());
            }
        }

        // التوجيه الذكي عند عدم العثور على نتائج
        if ($results->isEmpty()) {
            $branchNotice = ($targetBranch !== 'all' && isset($this->branchLabels[$targetBranch]))
                ? " في *{$this->branchLabels[$targetBranch]}*"
                : "";

            return "❌ *لم نجد أي صنف يطابق*: \"{$search}\"{$branchNotice}\n\n"
                 . "💡 *نصائح للوصول لنتيجة دقيقة:*\n"
                 . "• اكتب الكلمة الأساسية فقط للصنف (مثال: `رول اب`).\n"
                 . "• تأكد من اختيار الفرع الصحيح.\n"
                 . "• يمكنك البحث باستخدام الباركود مباشرة.\n\n"
                 . "📌 *استعلامات أخرى يمكنك تجربتها:*\n"
                 . "• `رصيد بنر 130` | `مبيعات اليوم`";
        }

        return $this->formatWhatsAppReport($search, $results, $targetBranch);
    }

    /**
     * البحث عن الأصناف ورصيدها في فرع محدد مع الترتيب بحسب المطابقة والحد الأقصى
     */
    protected function searchInBranch(string $connection, string $rawSearch, string $normalizedSearch): array
    {
        $maxResults = 5;

        $query = Item::on($connection)
            ->with([
                'baseUnit',
                'units.unit',
                'stocks.store',
            ])
            ->where(function ($q) use ($rawSearch, $normalizedSearch) {
                $q->where('name', 'like', "%{$rawSearch}%")
                  ->orWhere('aliases', 'like', "%{$rawSearch}%")
                  ->orWhereRaw("
                        LOWER(
                            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي'), 'ة', 'ه')
                        ) LIKE ?", ["%{$normalizedSearch}%"])
                  ->orWhereHas('barcodes', function ($bQ) use ($rawSearch) {
                      $bQ->where('barcode', 'like', "%{$rawSearch}%");
                  });
            })
            ->orderByRaw("
                CASE
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END
            ", [$rawSearch, "{$rawSearch}%"]);

        $totalMatches = $query->count();
        $items = $query->take($maxResults)->get();

        $mappedItems = $items->map(function ($item) {
            $baseUnitName = $item->baseUnit->name ?? 'قطعة';

            $storesStock = $item->stocks->map(function ($stock) use ($item) {
                $baseQty = (float) $stock->current_quantity;
                return [
                    'store_name' => $stock->store->name ?? 'المخزن الرئيسي',
                    'base_qty'   => $baseQty,
                    'breakdown'  => $this->calculateUnitBreakdown($item, $baseQty),
                ];
            });

            $totalBaseQty = $storesStock->sum('base_qty');

            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'total_breakdown' => $this->calculateUnitBreakdown($item, $totalBaseQty),
                'stores'          => $storesStock,
            ];
        });

        return [
            'total_count' => $totalMatches,
            'has_more'    => $totalMatches > $maxResults,
            'items'       => $mappedItems,
        ];
    }

    /**
     * تفكيك الكميات بناءً على مصفوفة الوحدات التابعة للصنف
     */
    protected function calculateUnitBreakdown(Item $item, float $baseQty): string
    {
        $baseUnitName = $item->baseUnit->name ?? 'قطعة';

        if ($baseQty == 0) {
            return "0 {$baseUnitName}";
        }

        $units = $item->units
            ->filter(fn($u) => (float) $u->conversion_factor > 1)
            ->sortByDesc(fn($u) => (float) $u->conversion_factor);

        if ($units->isEmpty()) {
            return "{$baseQty} {$baseUnitName}";
        }

        $remainingQty = abs($baseQty);
        $parts = [];

        foreach ($units as $itemUnit) {
            $factor = (float) $itemUnit->conversion_factor;
            $unitName = $itemUnit->unit->name ?? 'وحدة';

            if ($remainingQty >= $factor) {
                $unitQty = floor($remainingQty / $factor);
                $remainingQty = fmod($remainingQty, $factor);
                $parts[] = "{$unitQty} {$unitName}";
            }
        }

        if ($remainingQty > 0 || empty($parts)) {
            $formattedRemaining = (float) $remainingQty;
            $parts[] = "{$formattedRemaining} {$baseUnitName}";
        }

        $result = implode(' و ', $parts);

        return $baseQty < 0 ? "-{$result}" : $result;
    }

    /**
     * تنسيق التقرير النهائي لشاشة الواتساب
     */
    protected function formatWhatsAppReport(string $search, Collection $results, string $targetBranch): string
    {
        $output = "📦 *تقرير توفر المخزون*: _{$search}_\n";
        $output .= "-----------------------------------\n";

        $totalMoreItems = 0;

        foreach ($results as $branchKey => $branchData) {
            $label = $this->branchLabels[$branchKey] ?? $branchKey;
            $output .= "🏢 *الفرع*: {$label}\n";

            foreach ($branchData['items'] as $item) {
                $output .= "🔹 *{$item['name']}*\n";
                $output .= "  ├ إجمالي المخزون: *{$item['total_breakdown']}*\n";

                if ($item['stores']->count() > 1) {
                    foreach ($item['stores'] as $store) {
                        $output .= "  ├─ {$store['store_name']}: {$store['breakdown']}\n";
                    }
                }
            }

            if (!empty($branchData['has_more'])) {
                $totalMoreItems += ($branchData['total_count'] - $branchData['items']->count());
            }

            $output .= "\n";
        }

        if ($totalMoreItems > 0) {
            $output .= "-----------------------------------\n";
            $output .= "💡 *ملاحظة*: توجد *{$totalMoreItems} أصناف أخرى* تطابق كلمة \"{$search}\".\n";
            $output .= "يرجى تحديد البحث بدقة (مثال: *{$search} 150*).\n";
        }

        return trim($output);
    }

    /**
     * تطبيع النصوص العربية
     */
    protected function normalizeArabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        $text = preg_replace('/ى/u', 'ي', $text);
        $text = preg_replace('/ة/u', 'ه', $text);

        return trim(mb_strtolower($text));
    }
}
