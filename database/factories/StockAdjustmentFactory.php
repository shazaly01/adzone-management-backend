<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    /**
     * تعريف الحالة الافتراضية لبناء رأس مستند التسوية الجردية آلياً
     */
    public function definition(): array
    {
        return [
            // يتم توليد السلسلة والرقم التلقائي (ADJ-000x) عبر الـ boot method في الموديل
            'store_id'         => Store::factory(), // بناء مخزن تلقائي إن لم يمرر
            'adjustment_date'  => now(),
            'notes'            => 'مستند تسوية جردية تم إنشاؤه آلياً عبر نظام الفاكتوري للاختبارات',
            'user_id'          => User::factory(),  // بناء مستخدم تلقائي إن لم يمرر
            'journal_entry_id' => null,             // يتم ربطه ديناميكياً عبر الـ Service
        ];
    }
}
