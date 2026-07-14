<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * تشغيل تغذية الصلاحيات والأدوار في النظام.
     */
    public function run(): void
    {
        // 1. إعادة تعيين ذاكرة الصلاحيات المؤقتة لضمان تطبيق التغييرات فوراً دون تعليق المؤشرات
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';

        // 2. القائمة الشاملة والكاملة لكافة صلاحيات النظام
        $permissions = [
            // لوحة التحكم
            'dashboard.view',    // الصلاحية الأساسية لفتح النظام
            'dashboard.manager', // صلاحية المدير لرؤية الإحصائيات الحساسة

            // إدارة المستخدمين والأدوار
            'user.view', 'user.create', 'user.update', 'user.delete',
            'role.view', 'role.create', 'role.update', 'role.delete',

            // إدارة شجرة الحسابات الرئيسية
            'account.view', 'account.create', 'account.update', 'account.delete',

            // إدارة الخزائن المالية
            'treasury.view', 'treasury.create', 'treasury.update', 'treasury.delete',

            // إدارة البنوك والحسابات المصرفية
            'bank.view', 'bank.create', 'bank.update', 'bank.delete',

            // إدارة حسابات العملاء
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',

            // إدارة حسابات الموردين
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',

            // إدارة بنود المصروفات التشغيلية
            'expense.view', 'expense.create', 'expense.update', 'expense.delete',

            // إدارة المخازن والمستودعات
            'store.view', 'store.create', 'store.update', 'store.delete',

            // إدارة مجموعات الأصناف
            'category.view', 'category.create', 'category.update', 'category.delete',

            // إدارة وحدات القياس
            'unit.view', 'unit.create', 'unit.update', 'unit.delete',

            // إدارة دليل الأصناف والخدمات
            'item.view', 'item.create', 'item.update', 'item.delete',

            // موديول فواتير المشتريات ومردوداتها
            'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete',

            // موديول فواتير المبيعات ومردوداتها ملاءمة خامات الورشة
            'sale.view', 'sale.create', 'sale.update', 'sale.delete', 'sale.swap_raw_materials',

            // إدارة سندات القبض والصرف
            'voucher.view', 'voucher.create', 'voucher.update', 'voucher.delete',

            // إدارة التسويات الجردية المخزنية
            'stock_adjustment.view', 'stock_adjustment.create', 'stock_adjustment.update', 'stock_adjustment.delete',

            // بضاعة أول المدة (الأرصدة الافتتاحية للمخزن)
            'opening_stock.view', 'opening_stock.create', 'opening_stock.update', 'opening_stock.delete',

            // إدارة القيود اليومية المركبة
            'journal_entry.view', 'journal_entry.create', 'journal_entry.update', 'journal_entry.delete',

            // --- قسم التقارير الموحد المعزول ---
            'report.financial',         // التقارير المالية الإدارية الحساسة (ميزانية، أرباح وخسائر)
            'report.inventory',         // التقارير المخزنية الإدارية (تقييم المخزون)
            'report.account_statement', // تقرير كشف الحساب الرئيسي للشجرة
            'report.trial_balance',     // تقرير ميزان المراجعة
            'report.sub_ledger',        // [مستقلة وتفضيلية]: صلاحية كشف الحساب المساعد المفتوح للجميع

            // النسخ الاحتياطي والإعدادات العامة
            'backup.view', 'backup.create', 'backup.delete', 'backup.download', 'backup.restore',
            'setting.view', 'setting.update',
        ];

        // 3. إنبات وإنشاء الصلاحيات ذرياً في قاعدة البيانات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guardName]);
        }

        // --- 4. إنشاء الأدوار وتوزيع الصلاحيات هندسياً ---

        // أ. دور الـ Super Admin السيادي
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);

        // ب. دور الـ Admin (يرث كافة الصلاحيات المنبتة تلقائياً)
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $adminRole->givePermissionTo(Permission::all());

        // ج. دور مدخل البيانات (Data Entry)
        // صلاحياته تشغيلية بحتة ومحجوب تماماً عن التقارير المالية الإدارية باستثناء الحساب المساعد
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
            'journal_entry.view', 'journal_entry.create',
            'report.sub_ledger', // منحه صلاحية كشف الحساب المساعد بأمان تام دون كشف باقي الأسرار المالية
        ]);

        // د. دور المراجع (Auditor)
        // يملك كافة صلاحيات العرض العام ومخول لرؤية كافة تقارير النظام الإدارية والتشغيلية
        $auditorRole = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => $guardName]);

        $viewPermissions = Permission::where('name', 'like', '%.view')
            ->orWhere('name', 'like', 'report.%')
            ->pluck('name');

        $auditorRole->givePermissionTo($viewPermissions);
    }
}
