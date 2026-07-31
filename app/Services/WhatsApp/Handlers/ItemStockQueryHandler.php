<?php

namespace App\Services\WhatsApp\Handlers;

use App\Services\WhatsApp\Contracts\QueryHandlerInterface;
use App\Models\Item;
use Illuminate\Support\Collection;

class ItemStockQueryHandler implements QueryHandlerInterface
{
    /**
     * اسم النية الفريدة التي يعالجها الكلاس
     */
    public function getIntentName(): string
    {
        return 'item_stock';
    }

    /**
     * وصف دقيق وموجز للنية يُحقن تلقائياً في الـ System Prompt الخاص بالذكاء الاصطناعي
     */
    public function getDescription(): string
    {
        return 'استعلام عن رصيد الكميات المتوفرة من صنف معين أو عدة أصناف (مثل: رصيد بنر 130، جرد ورق 70 جرام)';
    }

    /**
     * تنفيذ الاستعلام وإعادة نص الرد البسيط والمباشر للواتساب
     *
     * @param array $parsedIntent
     * @return string
     */
    public function handle(array $parsedIntent): string
    {
        $search = trim($parsedIntent['item_name'] ?? $parsedIntent['search'] ?? '');
        $branchConnections = $parsedIntent['branch_connections'] ?? ['branch_main'];

        if (empty($search)) {
            return "⚠️ يرجى تحديد اسم الصنف أو الباركود للاستعلام عن المخزون.";
        }

        $results = collect();

        foreach ($branchConnections as $connection) {
            $branchResults = $this->searchInBranch($connection, $search);
            if (!empty($branchResults['items']) && $branchResults['items']->isNotEmpty()) {
                $results->put($connection, $branchResults);
            }
        }

        // التوجيه الذكي عند عدم العثور على نتائج في قواعد البيانات
        if ($results->isEmpty()) {
            return "❌ *لم نجد أي صنف يطابق*: \"{$search}\"\n\n"
                 . "💡 *نصائح للوصول لنتيجة دقيقة:*\n"
                 . "• اكتب الكلمة الأساسية فقط للصنف (مثال: اكتب `بنر` بدلاً من `بنر كوريا ممتاز`).\n"
                 . "• يمكنك البحث باستخدام الباركود الخاص بالصنف مباشرة.\n"
                 . "• تأكد من كتابة الأحرف بدون أخطاء إملائية.\n\n"
                 . "📌 *استعلامات أخرى يمكنك تجربتها:*\n"
                 . "• `كشف حساب شركة البركة` | `مبيعات اليوم`";
        }

        return $this->formatWhatsAppReport($search, $results);
    }

    /**
     * البحث عن الأصناف ورصيدها في فرع محدد مع الترتيب بحسب المطابقة والحد الأقصى
     */
    protected function searchInBranch(string $connection, string $search): array
    {
        $maxResults = 5;

        $query = Item::on($connection)
            ->with([
                'baseUnit',
                'units.unit',
                'stocks.store',
            ])
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('aliases', 'like', "%{$search}%")
                  ->orWhereHas('barcodes', function ($bQ) use ($search) {
                      $bQ->where('barcode', 'like', "%{$search}%");
                  });
            })
            ->orderByRaw("
                CASE
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END
            ", [$search, "{$search}%"]);

        $totalMatches = $query->count();
        $items = $query->take($maxResults)->get();

        $mappedItems = $items->map(function ($item) {
            $baseUnitName = $item->baseUnit->name ?? 'وحدة';

            $storesStock = $item->stocks->map(function ($stock) use ($item) {
                $baseQty = (float) $stock->current_quantity;
                $breakdown = $this->calculateUnitBreakdown($item, $baseQty);

                return [
                    'store_name' => $stock->store->name ?? 'المخزن الرئيسي',
                    'base_qty'   => $baseQty,
                    'breakdown'  => $breakdown,
                ];
            });

            $totalBaseQty = $storesStock->sum('base_qty');
            $totalBreakdown = $this->calculateUnitBreakdown($item, $totalBaseQty);

            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'base_unit'       => $baseUnitName,
                'total_base_qty'  => $totalBaseQty,
                'total_breakdown' => $totalBreakdown,
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
        $baseUnitName = $item->baseUnit->name ?? 'وحدة';

        if ($baseQty == 0) {
            return "0 {$baseUnitName}";
        }

        $units = $item->units
            ->filter(fn($u) => (float) $u->conversion_factor > 1)
            ->sortByDesc(fn($u) => (float) $u->conversion_factor);

        if ($units->isEmpty()) {
            return "{$baseQty} {$baseUnitName}";
        }

        $remainingQty = $baseQty;
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

        return implode(' و ', $parts);
    }

    /**
     * تنسيق التقرير النهائي لشاشة الواتساب بطريقة موجزة وسريعة القراءة
     */
    protected function formatWhatsAppReport(string $search, Collection $results): string
    {
        $output = "📦 *تقرير توفر المخزون*: _{$search}_\n";
        $output .= "-----------------------------------\n";

        $totalMoreItems = 0;

        foreach ($results as $branch => $branchData) {
            $branchLabel = ucfirst(str_replace(['branch_', '_'], ['', ' '], $branch));
            $output .= "🏢 *الفرع*: {$branchLabel}\n";

            $items = $branchData['items'];
            foreach ($items as $item) {
                $output .= "🔹 *{$item['name']}*\n";
                $output .= "  ├ إجمالي المخزون: *{$item['total_breakdown']}*\n";

                if ($item['stores']->count() > 1) {
                    foreach ($item['stores'] as $store) {
                        $output .= "  ├─ {$store['store_name']}: {$store['breakdown']}\n";
                    }
                }
            }

            if (!empty($branchData['has_more'])) {
                $moreInBranch = $branchData['total_count'] - $items->count();
                $totalMoreItems += $moreInBranch;
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
}
