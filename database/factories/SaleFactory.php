<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SaleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 5000);
        $tax = $subtotal * 0.15; // احتساب ضريبة القيمة المضافة الافتراضية 15%
        $grandTotal = $subtotal + $tax;

        return [
            'invoice_type'     => 'sale',
            // لا نولد التسلسل والرقم هنا لأن دالة boot في موديل Sale تتولى ذلك برمجياً عند الحفظ الفعلي
            'store_id'         => Store::factory(),
            'customer_id'      => Customer::factory(),
            'user_id'          => User::factory(),
            'journal_entry_id' => null, // يترك فارغاً لأنه يعتمد على معالجة الـ Service لاحقاً
            'invoice_date'     => Carbon::now()->format('Y-m-d H:i:s'),
            'payment_type'     => $this->faker->randomElement(['cash', 'credit']),
            'subtotal'         => $subtotal,
            'discount_amount'  => 0.00,
            'tax_amount'       => $tax,
            'grand_total'      => $grandTotal,
            'notes' => $this->faker->sentence(),
        ];
    }
}
