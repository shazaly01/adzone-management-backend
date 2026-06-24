<?php

namespace Database\Factories;

use App\Models\SaleItem;
use App\Models\Sale;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = SaleItem::class;

    /**
     * تعريف الحالة الافتراضية لأسطر المبيعات بناءً على معمارية الوحدات الجديدة
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 50);
        $unitPrice = $this->faker->randomFloat(2, 20, 1000);
        $subtotal = $quantity * $unitPrice;
        $discountAmount = 0.00;
        $grandTotal = $subtotal - $discountAmount;

        return [
            'sale_id'      => Sale::factory(),
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
            'unit_price'      => $unitPrice,
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'grand_total'     => $grandTotal,
        ];
    }
}
