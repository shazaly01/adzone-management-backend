<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'parent_id'       => null,
            'name'            => $this->faker->unique()->words(2, true) . ' Account',
            'code'            => $this->faker->unique()->numerify('####'),
            // [تم التعديل]: لتطابق القيم المسموحة في قاعدة البيانات
            'type'            => $this->faker->randomElement(['system', 'cash', 'bank', 'customer', 'supplier', 'expense', 'income', 'normal']),
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ];
    }
}
