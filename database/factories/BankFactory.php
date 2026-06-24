<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bank::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'           => $this->faker->company() . ' Bank',
            'account_number' => $this->faker->unique()->numerify('##########'),
            'iban'           => $this->faker->unique()->iban('SA'),
            'account_id'     => function () {
                // البحث عن حساب البنوك الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_BANKS)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'البنوك',
                        'code' => Account::CODE_BANKS,
                        'type' => 'bank',
                    ]);
                }

                return $parentAccount->id;
            },
            'is_active'      => true,
        ];
    }
}
