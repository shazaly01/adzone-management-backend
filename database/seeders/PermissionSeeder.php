<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إعادة تعيين ذاكرة الصلاحيات المؤقتة لضمان تطبيق التغييرات فوراً
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';

        // 2. قائمة شاملة ومطورة بكافة صلاحيات النظام المالي الجديد
        $permissions = [
            'dashboard.view',

            // إدارة المستخدمين والأدوار
            'user.view', 'user.create', 'user.update', 'user.delete',
            'role.view', 'role.create', 'role.update', 'role.delete',

            // إدارة شجرة الحسابات الرئيسية (حساسة)
            'account.view', 'account.create', 'account.update', 'account.delete',

            // إدارة الخزائن
            'treasury.view', 'treasury.create', 'treasury.update', 'treasury.delete',

            // إدارة البنوك
            'bank.view', 'bank.create', 'bank.update', 'bank.delete',

            // إدارة العملاء
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',

            // إدارة الموردين
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',

            // إدارة بنود المصروفات
            'expense.view', 'expense.create', 'expense.update', 'expense.delete',

            'store.view', 'store.create', 'store.update', 'store.delete',

            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            // موديول دليل الوحدات
            'unit.view',
            'unit.create',
            'unit.update',
            'unit.delete',

            'item.view', 'item.create', 'item.update', 'item.delete',


            // موديول المشتريات ومردوداتها
'purchase.view',
'purchase.create',
'purchase.update', // التعديل الفوري متاح ومحمي بالصلاحية
'purchase.delete',

// موديول المبيعات ومردوداتها
'sale.view',
'sale.create',
'sale.update',     // التعديل الفوري متاح ومحمي بالصلاحية
'sale.delete',
'sale.swap_raw_materials',

       "voucher.view",'voucher.create','voucher.update','voucher.delete',

       'stock_adjustment.view',
'stock_adjustment.create',
'stock_adjustment.update',
'stock_adjustment.delete',

       'opening_stock.view',
'opening_stock.create',
'opening_stock.update',
'opening_stock.delete',

// إدارة القيود اليومية وسندات الصرف والقبض المركبة
            'journal_entry.view', 'journal_entry.create', 'journal_entry.update', 'journal_entry.delete',

            // صلاحيات خاصة بالتقارير المالية وكشوفات الحساب
            'report.account_statement', 'report.trial_balance',

            // النسخ الاحتياطي والإعدادات العامة
            'backup.view', 'backup.create', 'backup.delete', 'backup.download',
            'setting.view', 'setting.update',
        ];

        // إنشاء الصلاحيات في قاعدة البيانات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guardName]);
        }

        // --- 3. إنشاء الأدوار وتوزيع الصلاحيات بدقة ---

        // أ. دور Super Admin (يملك كل شيء عبر Gate::before فلا داعي لمنحه صلاحيات يدوياً)
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);

        // ب. دور Admin (مدير النظام - يملك كل الصلاحيات المسجلة)
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $adminRole->givePermissionTo(Permission::all());

        // ج. دور Data Entry (مدخل بيانات)
        // يستطيع المشاهدة والإضافة للكيانات التشغيلية، وممنوع من الحذف، التعديل المالي، أو رؤية التقارير الحساسة والشجرة
        $dataEntryRole = Role::firstOrCreate(['name' => 'Data Entry', 'guard_name' => $guardName]);
        $dataEntryRole->givePermissionTo([
            'dashboard.view',
            'treasury.view', 'treasury.create',
            'bank.view', 'bank.create',
            'customer.view', 'customer.create', 'customer.update',
            'supplier.view', 'supplier.create', 'supplier.update',
            'expense.view', 'expense.create',
            'store.view', 'store.create',
            'category.view',
            'unit.view',
             'item.view', 'item.create',
            'journal_entry.view', 'journal_entry.create', // مدخل البيانات ينشئ السندات ولا يعدلها بعد الترحيل أو يحذفها
        ]);

        // د. دور Auditor (المراجع) - يرى كل شيء ولا يغير شيئاً
        $auditorRole = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => $guardName]);

        // المراجع يحصل على أي صلاحية تحتوي على كلمة view أو تعبر عن تقرير
        $viewPermissions = Permission::where('name', 'like', '%.view')
            ->orWhere('name', 'like', 'report.%')
            ->pluck('name');

        $auditorRole->givePermissionTo($viewPermissions);
    }
}
