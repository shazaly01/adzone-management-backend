<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. زرع الصلاحيات والأدوار الأساسية في النظام
            PermissionSeeder::class,

            // 2. زرع الهيكل البنيوي والشجرة المحاسبية (حل مشكلة خطأ الـ Account 1101)
            FinancialStructureSeeder::class,

            // 3. زرع حسابات المستخدمين وربطهم بالأدوار والصلاحيات
            UserSeeder::class,
        ]);
    }
}
