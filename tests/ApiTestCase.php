<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\PermissionSeeder; // <-- استيراد مباشر أفضل
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    // --- تحديث أسماء المستخدمين لتعكس أدوارهم الجديدة ---
    protected User $superAdmin;
    protected User $adminUser;
    protected User $dataEntryUser;
    protected User $auditorUser;

    /**
     * هذا الإعداد يتم تشغيله قبل كل اختبار في الكلاسات التي ترث هذا الكلاس.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. تشغيل الـ Seeder لإنشاء الأدوار والصلاحيات
        $this->seed(PermissionSeeder::class);

        // 2. إنشاء مستخدمين بأدوار مختلفة
        $this->superAdmin = User::factory()->create()->assignRole('Super Admin');
        $this->adminUser = User::factory()->create()->assignRole('Admin');
        $this->dataEntryUser = User::factory()->create()->assignRole('Data Entry');
        $this->auditorUser = User::factory()->create()->assignRole('Auditor');

        // 3. تسجيل الدخول افتراضيًا كمستخدم Super Admin
        // يمكن لأي اختبار تغيير المستخدم باستخدام Sanctum::actingAs(...)
        Sanctum::actingAs($this->superAdmin);
    }
}
