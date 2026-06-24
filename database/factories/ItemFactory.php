<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Category;
use App\Models\Unit;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * تعريف الحالة الافتراضية لموديل الصنف
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $profitMargin = $this->faker->randomFloat(2, 10, 50); // نسبة ربح افتراضية بين 10% و 50%

        return [
            'category_id'   => function () {
                return Category::first()?->id ?? Category::factory()->create()->id;
            },
            'category_path' => null,
            'name'          => 'صنف ' . $this->faker->unique()->words(2, true),
            'item_type'     => $this->faker->randomElement(['product', 'service']),
            'profit_margin' => $profitMargin,

            // التعديل المعماري: ربط الصنف بالوحدة الصغرى القياسية كمرجع أساسي
            'base_unit_id'  => function () {
                return Unit::first()?->id ?? Unit::factory()->create()->id;
            },

            'is_active'     => true,
        ];
    }

    /**
     * إعدادات إضافية لحقن السطر التأسيسي الأول في مصفوفة الوحدات اللانهائية فور إنشاء الصنف
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Item $item) {
            $cost = $this->faker->randomFloat(4, 10, 500);
            $price = $cost * (1 + ($item->profit_margin / 100));

            // إنشاء الوحدة الأساسية الأولى داخل جدول المصفوفة لحماية المنظومة والخدمات من الانهيار أثناء الفحص
            ItemUnit::create([
                'item_id'           => $item->id,
                'unit_id'           => $item->base_unit_id,
                'conversion_factor' => 1.0000, // الوحدة الأساسية معامل تحويلها دائماً 1 صحيح
                'cost'              => round($cost, 2),
                'price'             => round($price, 2),
            ]);
        });
    }
}
