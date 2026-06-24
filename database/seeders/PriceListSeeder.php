<?php

namespace Database\Seeders;

use App\Models\PriceList;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. تثبيت سعر الجمهور كفئة افتراضية أولى للنظام
        PriceList::updateOrCreate(
            ['id' => 1],
            [
                'name'       => 'سعر الجمهور',
                'is_default' => true,
            ]
        );

        // 2. تثبيت سعر الجملة كفئة سعرية ثانية للمصفوفة
        PriceList::updateOrCreate(
            ['id' => 2],
            [
                'name'       => 'سعر الجملة',
                'is_default' => false,
            ]
        );
    }
}
