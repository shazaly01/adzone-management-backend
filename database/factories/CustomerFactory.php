<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'         => $this->faker->name(),
            'phone'        => $this->faker->phoneNumber(),
            'email'        => $this->faker->unique()->safeEmail(),
            'credit_limit' => $this->faker->randomFloat(2, 1000, 50000), // حد ائتمان عشوائي بين 1000 و 50000
            'account_id'   => function () {
                // البحث عن حساب العملاء الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_CUSTOMERS)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'العملاء',
                        'code' => Account::CODE_CUSTOMERS,
                        'type' => 'customer',
                    ]);
                }

                return $parentAccount->id;
            },
        ];
    }
}
