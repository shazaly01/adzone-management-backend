<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إنشاء مستخدم النظام الآلي الافتراضي (System Automated User)
        // يثبت بـ ID = 1 ليكون المرجع الصامد لأي حركات افتتاحية أو عمليات مخزنية مؤتمتة في الخلفية
        $systemUser = User::create([
            'id'                => 1,
            'full_name'         => 'System Engine',
            'username'          => 'system_core',
            'email'             => 'system@core.internal',
            'password'          => bcrypt('SystemSecureToken2026!'), // كلمة مرور معقدة وغير مستخدمة للبشر
            'email_verified_at' => now(),
        ]);
        $systemUser->assignRole('Super Admin');

        // 2. إنشاء مستخدم Super Admin (المدير العام)
        $superAdmin = User::create([
            'full_name'         => 'Super Admin',
            'username'          => 'superadmin',
            'email'             => 'superadmin@app.com',
            'password'          => bcrypt('12345678'), // كلمة مرور موحدة لسهولة التطوير
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // 3. إنشاء مستخدم Admin (مدير النظام)
        $adminUser = User::create([
            'full_name'         => 'Admin User',
            'username'          => 'admin',
            'email'             => 'admin@app.com',
            'password'          => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('Admin');

        // 4. إنشاء مستخدم Data Entry (مدخل بيانات)
        $dataEntryUser = User::create([
            'full_name'         => 'Data Entry User',
            'username'          => 'dataentry',
            'email'             => 'dataentry@app.com',
            'password'          => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        $dataEntryUser->assignRole('Data Entry');

        // 5. إنشاء مستخدم Auditor (مراجع)
        $auditorUser = User::create([
            'full_name'         => 'Auditor User',
            'username'          => 'auditor',
            'email'             => 'auditor@app.com',
            'password'          => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        $auditorUser->assignRole('Auditor');
    }
}
