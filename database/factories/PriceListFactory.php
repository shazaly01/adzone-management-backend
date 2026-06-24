<?php

namespace Database\Factories;

use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = PriceList::class;

    /**
     * تعريف الحالة الافتراضية لقوائم سياسات التسعير بدون حقول زائدة
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['قائمة أسعار الجمهور', 'قائمة أسعار الجملة', 'قائمة أسعار الموزعين والمعارض']),
        ];
    }
}
