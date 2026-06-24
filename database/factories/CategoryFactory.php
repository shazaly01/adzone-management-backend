<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null, // افتراضياً يكون تصنيفاً رئيسياً، ويمكن تعديله في الاختبار لتجربة الشجرة
            'name'      => 'تصنيف ' . $this->faker->unique()->words(2, true),
            'path'      => null, // نتركه فارغاً لأن دالة boot في الموديل ستتولى بناءه فوراً بعد الإنشاء
            'is_active' => true,
        ];
    }
}
