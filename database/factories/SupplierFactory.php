<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->company() . ' For Trading',
            'phone'      => $this->faker->phoneNumber(),
            'tax_number' => $this->faker->unique()->numerify('15###########'), // الرقم الضريبي المكون من 15 رقم
            'account_id' => function () {
                // البحث عن حساب الموردين الرئيسي أو إنشاؤه تلقائياً لبيئة الاختبار
                $parentAccount = Account::where('code', Account::CODE_SUPPLIERS)->first();

                if (!$parentAccount) {
                    $parentAccount = Account::factory()->create([
                        'name' => 'الموردين',
                        'code' => Account::CODE_SUPPLIERS,
                        'type' => 'supplier',
                    ]);
                }

                return $parentAccount->id;
            },
        ];
    }
}
