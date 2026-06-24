<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PurchaseFactory extends Factory
{
    /**
     * اسم الموديل المرتبط بهذا الـ Factory
     *
     * @var string
     */
    protected $model = Purchase::class;

    /**
     * تعريف الحالة الافتراضية لموديل رأس مستند المشتريات
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 1000, 10000);
        $tax = $subtotal * 0.15; // احتساب ضريبة افتراضية صريحة 15%
        $grandTotal = $subtotal + $tax;

        return [
            'invoice_type'     => 'purchase',
            'store_id'         => Store::factory(),
            'supplier_id'      => Supplier::factory(),
            'user_id'          => User::factory(),
            'journal_entry_id' => null, // يتم توليده ميكانيكياً عبر الـ Service وقت التنفيذ الحقيقي
            'invoice_date'     => Carbon::now()->format('Y-m-d'),
            'payment_type'     => $this->faker->randomElement(['cash', 'credit']),
            'subtotal'         => $subtotal,
            'discount_amount'  => 0.00,
            'tax_amount'       => $tax,
            'grand_total'      => $grandTotal,
            'notes'            => $this->faker->sentence(),
        ];
    }
}
