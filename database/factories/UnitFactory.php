<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // إضافة كلمات عشوائية لضمان عدم تكرار اسم الوحدة أثناء الاختبارات
            'name'       => 'وحدة ' . $this->faker->unique()->word(),
            'short_name' => $this->faker->unique()->lexify('???'),
            'is_active'  => true,
        ];
    }
}
