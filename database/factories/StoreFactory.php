<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => 'مستودع ' . $this->faker->unique()->word(),
            'location'   => $this->faker->address(),
            'account_id' => function () {
                // البحث عن حساب المخزون الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_INVENTORY)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'المخزون',
                        'code' => Account::CODE_INVENTORY,
                        'type' => 'system',
                    ]);
                }

                return $parentAccount->id;
            },
            'is_active'  => true,
        ];
    }
}
