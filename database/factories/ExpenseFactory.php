<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => 'مصروف ' . $this->faker->unique()->word(),
            'is_active'  => true,
            'account_id' => function () {
                // البحث عن حساب المصروفات الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_EXPENSES)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'المصروفات التشغيلية',
                        'code' => Account::CODE_EXPENSES,
                        'type' => 'expense',
                    ]);
                }

                return $parentAccount->id;
            },
        ];
    }
}
