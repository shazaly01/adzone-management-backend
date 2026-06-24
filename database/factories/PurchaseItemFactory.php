<?php

namespace Database\Factories;

use App\Models\PurchaseItem;
use App\Models\Purchase;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = PurchaseItem::class;

    /**
     * تعريف الحالة الافتراضية لأسطر المشتريات بناءً على معمارية الوحدات الجديدة
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitCost = $this->faker->randomFloat(2, 10, 500);
        $subtotal = $quantity * $unitCost;

        return [
            'purchase_id'  => Purchase::factory(),
            'item_id'      => function () {
                return Item::first()?->id ?? Item::factory()->create()->id;
            },
            // التعديل المعماري: جلب سطر الوحدة المتوافق والموجود مسبقاً للصنف عبر الـ بعد الإنشاء الافتراضي
            'item_unit_id' => function (array $attributes) {
                $itemId = $attributes['item_id'];
                return ItemUnit::where('item_id', $itemId)->first()?->id
                    ?? ItemUnit::create([
                        'item_id' => $itemId,
                        'unit_id' => Item::find($itemId)->base_unit_id,
                        'conversion_factor' => 1.0000,
                        'cost' => 0.00,
                        'price' => 0.00
                    ])->id;
            },
            'quantity'        => $quantity,
            'unit_cost'       => $unitCost,
            'subtotal'        => $subtotal,
            'discount_amount' => 0.00,
            'grand_total'     => $subtotal,
        ];
    }
}
