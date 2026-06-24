<?php

namespace Database\Factories;

use App\Models\StockAdjustmentItem;
use App\Models\StockAdjustment;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentItemFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = StockAdjustmentItem::class;

    /**
     * تعريف الحالة الافتراضية لبناء أسطر وتفاصيل الأصناف الفاشلة أو الفائضة بناءً على معمارية الوحدات الجديدة
     */
    public function definition(): array
    {
        $bookQuantity = 10.00;
        $physicalQuantity = 8.00; // الافتراضي عجز بمقدار حبتين
        $difference = $physicalQuantity - $bookQuantity;

        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'item_id'             => Item::factory(),

            // التعديل المعماري: جلب سطر الوحدة المتوافق والموجود مسبقاً للصنف عبر المصفوفة المحدثة
            'item_unit_id'        => function (array $attributes) {
                $itemId = $attributes['item_id'];
                return ItemUnit::where('item_id', $itemId)->first()?->id
                    ?? ItemUnit::create([
                        'item_id'           => $itemId,
                        'unit_id'           => Item::find($itemId)->base_unit_id,
                        'conversion_factor' => 1.0000,
                        'cost'              => 0.00,
                        'price'             => 0.00
                    ])->id;
            },

            'book_quantity'       => $bookQuantity,
            'physical_quantity'   => $physicalQuantity,
            'quantity_difference' => $difference,
            'unit_cost'           => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
