<?php

namespace Database\Factories;

use App\Models\Voucher;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * تعريف الحالة الافتراضية لبناء السند المالي آلياً في بيئة الاختبارات
     */
    public function definition(): array
    {
        return [
            'voucher_type'     => 'payment', // الافتراضي سند صرف
            'voucher_sequence' => 1,         // يتم إعادة احتسابه تلقائياً في الموديل بوت
            'voucher_number'   => 'PAY-0001', // يتم توليده تلقائياً في الموديل بوت
            'account_id'       => Account::factory(), // بناء حساب مستهدف تلقائي
            'payment_method'   => 'cash',
            'fund_account_id'  => Account::factory(), // بناء حساب نقدية تلقائي
            'amount'           => $this->faker->randomFloat(2, 100, 5000),
            'voucher_date'     => now(),
            'notes'            => 'سند مالي تم إنشاؤه تلقائياً عبر مصنع بيئة الاختبارات',
            'user_id'          => User::factory(), // بناء موظف منشئ تلقائي
            'journal_entry_id' => null, // يتم ربطه حركياً في الخدمة
        ];
    }
}
