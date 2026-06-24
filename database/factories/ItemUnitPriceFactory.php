<?php

namespace Database\Factories;

use App\Models\ItemUnitPrice;
use App\Models\ItemUnit;
use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemUnitPriceFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = ItemUnitPrice::class;

    /**
     * تعريف الحالة الافتراضية لجدول أسعار الوحدات بناءً على فئات قوائم الأسعار
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountPercentage = $this->faker->randomFloat(2, 0, 20); // خصم عشوائي متاح بين 0% و 20%

        return [
            // توليد سطر مصفوفة وحدات مستقل تلقائياً
            'item_unit_id'        => ItemUnit::factory(),

            // حقن نفس الـ item_id العائد لسطر المصفوفة لضمان التناسق الهيكلي ومنع تضارب البيانات
            'item_id'             => function (array $attributes) {
                return ItemUnit::find($attributes['item_unit_id'])->item_id;
            },

            // ربط السعر بفئة قائمة أسعار (جمهور، جملة...) موجودة مسبقاً أو إنشاء واحدة جديدة
            'price_list_id'       => function () {
                return PriceList::first()?->id ?? PriceList::factory()->create()->id;
            },

            'discount_percentage' => $discountPercentage,

            // احتساب السعر المالي بناءً على السعر الأساسي للوحدة بعد تطبيق نسبة الخصم المشتقة
            'price'               => function (array $attributes) {
                $basePrice = ItemUnit::find($attributes['item_unit_id'])->price ?? 100.0000;
                $finalPrice = $basePrice * (1 - ($attributes['discount_percentage'] / 100));
                return round($finalPrice, 4);
            },
        ];
    }
}
