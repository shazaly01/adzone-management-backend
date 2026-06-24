<?php

namespace Database\Factories;

use App\Models\OpeningStockItem;
use App\Models\OpeningStock;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpeningStockItemFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = OpeningStockItem::class;

    /**
     * تعريف الحالة الافتراضية لأسطر أرصدة أول المدة بناءً على معمارية الوحدات الجديدة
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 1, 100);
        $unitCost = $this->faker->randomFloat(2, 10, 500);
        $subtotal = $quantity * $unitCost;

        return [
            'opening_stock_id' => OpeningStock::factory(),
            'item_id'          => Item::factory(),

            // التعديل المعماري: جلب سطر الوحدة المتوافق والموجود مسبقاً للصنف عبر مصفوفة الوحدات المحدثة
            'item_unit_id'     => function (array $attributes) {
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

            'quantity'         => $quantity,
            'unit_cost'        => $unitCost,
            'subtotal'         => $subtotal,
        ];
    }
}
