<?php

namespace Database\Factories;

use App\Models\Treasury;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasuryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Treasury::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => 'خزينة ' . $this->faker->unique()->word(),
            'account_id' => function () {
                // البحث عن حساب الخزائن الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_TREASURY)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'الخزائن',
                        'code' => Account::CODE_TREASURY,
                        'type' => 'cash',
                    ]);
                }

                return $parentAccount->id;
            },
            'is_active'  => true,
        ];
    }
}
