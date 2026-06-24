<?php

namespace Database\Factories;

use App\Models\ItemBarcode;
use App\Models\ItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemBarcodeFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = ItemBarcode::class;

    /**
     * تعريف الحالة الافتراضية لجدول باركود الوحدات التابع للمصفوفة
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // توليد سطر مصفوفة وحدات مستقل تلقائياً
            'item_unit_id' => ItemUnit::factory(),

            // حقن نفس الـ item_id العائد لسطر المصفوفة لضمان التناسق الهيكلي في قاعدة البيانات
            'item_id'      => function (array $attributes) {
                return ItemUnit::find($attributes['item_unit_id'])->item_id;
            },

            'barcode'      => $this->faker->unique()->ean13(),
        ];
    }
}
