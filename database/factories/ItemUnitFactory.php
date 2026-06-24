<?php

namespace Database\Factories;

use App\Models\ItemUnit;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemUnitFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = ItemUnit::class;

    /**
     * تعريف الحالة الافتراضية لأسطر مصفوفة الوحدات ومعاملات التحويل
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = $this->faker->randomFloat(4, 5, 500);
        $price = $cost * $this->faker->randomFloat(4, 1.1, 1.6); // هامش ربح تلقائي بين 10% و 60%

        return [
            'item_id'           => Item::factory(),
            'unit_id'           => Unit::factory(),
            'conversion_factor' => $this->faker->randomFloat(4, 1, 24), // متاح حبة (1) أو كرتون (مثلاً 12 أو 24)
            'cost'              => round($cost, 4),
            'price'             => round($price, 4),
        ];
    }
}
