<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class FinancialStructureSeeder extends Seeder
{
    /**
     * تشغيل بذر شجرة الحسابات السيادية الموسعة مضافاً إليها طبيعة الحساب صراحة (مدين/دائن)
     */
    public function run(): void
    {
        // =========================================================================
        // 1. الحسابات الأب الرئيسية على المستوى الأول (Level 1)
        // =========================================================================

        // 1. الأصول (Assets) -> مدين
        $assets = Account::updateOrCreate(
            ['code' => '1'],
            [
                'parent_id'       => null,
                'name'            => 'الأصول',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // 2. الالتزامات وحقوق الملكية (Liabilities & Equity) -> دائن
        $liabilitiesAndEquity = Account::updateOrCreate(
            ['code' => '2'],
            [
                'parent_id'       => null,
                'name'            => 'الالتزامات وحقوق الملكية',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // 3. الإيرادات (Income) -> دائن
        $income = Account::updateOrCreate(
            ['code' => '3'],
            [
                'parent_id'       => null,
                'name'            => 'الإيرادات',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // 4. المصروفات وتكلفة النشاط (Expenses & COGS) -> مدين
        $expenses = Account::updateOrCreate(
            ['code' => '4'],
            [
                'parent_id'       => null,
                'name'            => 'المصروفات وتكلفة النشاط',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // =========================================================================
        // 2. الحسابات الفرعية المنسقة على المستوى الثاني (Level 2)
        // =========================================================================

        // --- تفريعات الأصول (كود 1) ---
        $currentAssets = Account::updateOrCreate(
            ['code' => '11'],
            [
                'parent_id'       => $assets->id,
                'name'            => 'الأصول المتداولة',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        $fixedAssets = Account::updateOrCreate(
            ['code' => '12'],
            [
                'parent_id'       => $assets->id,
                'name'            => 'الأصول غير المتداولة (الثابتة)',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // --- تفريعات الالتزامات وحقوق الملكية (كود 2) ---
        $currentLiabilities = Account::updateOrCreate(
            ['code' => '21'],
            [
                'parent_id'       => $liabilitiesAndEquity->id,
                'name'            => 'الالتزامات المتداولة',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        $equity = Account::updateOrCreate(
            ['code' => '22'],
            [
                'parent_id'       => $liabilitiesAndEquity->id,
                'name'            => 'حقوق الملكية (رأس المال والاحتياطيات)',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // --- تفريعات الإيرادات (كود 3) ---
        $operatingRevenues = Account::updateOrCreate(
            ['code' => '31'],
            [
                'parent_id'       => $income->id,
                'name'            => 'الإيرادات التشغيلية الرئيسيّة',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        $otherRevenues = Account::updateOrCreate(
            ['code' => '32'],
            [
                'parent_id'       => $income->id,
                'name'            => 'إيرادات وأرباح أخرى هامشية',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // --- تفريعات المصروفات (كود 4) ---
        $operatingExpenses = Account::updateOrCreate(
            ['code' => '41'],
            [
                'parent_id'       => $expenses->id,
                'name'            => 'المصروفات التشغيلية والإدارية العمومية',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        $costOfGoodsSold = Account::updateOrCreate(
            ['code' => '42'],
            [
                'parent_id'       => $expenses->id,
                'name'            => 'تكلفة البضاعة المباعة والنشاط حركياً',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // =========================================================================
        // 3. الحسابات التشغيلية والنهائية التفرعية على المستوى الثالث (Level 3)
        // =========================================================================

        // الحسابات التابعة لـ الأصول المتداولة (11) -> مدين
        Account::updateOrCreate(
            ['code' => '1101'],
            [
                'parent_id'       => $currentAssets->id,
                'name'            => 'حساب الخزائن الرئيسي النقدي',
                'type'            => 'cash',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '1102'],
            [
                'parent_id'       => $currentAssets->id,
                'name'            => 'حساب البنوك والمصارف الرئيسي',
                'type'            => 'bank',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '1103'],
            [
                'parent_id'       => $currentAssets->id,
                'name'            => 'حساب ذمم العملاء المساعد الإجمالي',
                'type'            => 'customer',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '1104'],
            [
                'parent_id'       => $currentAssets->id,
                'name'            => 'المخزون السلعي وبضاعة المستودعات الرئيسية',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '1105'],
            [
                'parent_id'       => $currentAssets->id,
                'name'            => 'حساب حسابات ذمم الضرائب - ضريبة القيمة المضافة المدخلة',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ الأصول الثابتة (12)
        Account::updateOrCreate(
            ['code' => '1201'],
            [
                'parent_id'       => $fixedAssets->id,
                'name'            => 'حساب العقارات والآلات والسيارات والمعدات الفنية',
                'type'            => 'system',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '1202'],
            [
                'parent_id'       => $fixedAssets->id,
                'name'            => 'مجمع إهلاك الأصول الثابتة المتراكم (حساب دائن عكسي)',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ الالتزامات المتداولة (21) -> دائن
        Account::updateOrCreate(
            ['code' => '2101'],
            [
                'parent_id'       => $currentLiabilities->id,
                'name'            => 'حساب ذمم الموردين والمقاولين الإجمالي',
                'type'            => 'supplier',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '2102'],
            [
                'parent_id'       => $currentLiabilities->id,
                'name' => 'حساب أمانات الضرائب الجارية - ضريبة القيمة المضافة المخرجة',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // [إضافة السيّدر الآمن]: حساب ذمم المصممين المساعد التابع للالتزامات المتداولة (21) -> دائن
        Account::updateOrCreate(
            ['code' => '2103'],
            [
                'parent_id'       => $currentLiabilities->id,
                'name'            => 'حساب ذمم المصممين المستحقة الإجمالي',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ حقوق الملكية (22) -> دائن
        Account::updateOrCreate(
            ['code' => '2201'],
            [
                'parent_id'       => $equity->id,
                'name'            => 'حساب رأس المال المدفوع والمستثمر نقداً',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '2202'],
            [
                'parent_id'       => $equity->id,
                'name'            => 'حساب رأس مال الأصول الثابتة والمخزنية الافتتاحية',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '2203'],
            [
                'parent_id'       => $equity->id,
                'name'            => 'حساب الأرباح والخسائر المرحلة / المبقاة المتراكمة',
                'type'            => 'system',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ الإيرادات التشغيلية (31) -> دائن
        Account::updateOrCreate(
            ['code' => '3101'],
            [
                'parent_id'       => $operatingRevenues->id,
                'name'            => 'حساب إيرادات مبيعات البضائع والمنتجات السلعية الإجمالي',
                'type'            => 'income',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ الإيرادات الأخرى (32) -> دائن
        Account::updateOrCreate(
            ['code' => '3201'],
            [
                'parent_id'       => $otherRevenues->id,
                'name'            => 'أرباح وفروقات جرد المخازن التلقائية التكتيكية',
                'type'            => 'income',
                'nature'          => 'credit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ المصروفات الإدارية والعمومية (41) -> مدين
        Account::updateOrCreate(
            ['code' => '4101'],
            [
                'parent_id'       => $operatingExpenses->id,
                'name'            => 'حساب المصروفات التشغيلية الإدارية والعمومية الشامل',
                'type'            => 'expense',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        Account::updateOrCreate(
            ['code' => '4102'],
            [
                'parent_id'       => $operatingExpenses->id,
                'name'            => 'خسائر ومصروفات عجز جرد الأصناف المخزنية التلقائية',
                'type'            => 'expense',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // [إضافة السيّدر الآمن]: حساب مصروف عمولات التصميم التابع للمصروفات التشغيلية (41) -> مدين
        Account::updateOrCreate(
            ['code' => '4103'],
            [
                'parent_id'       => $operatingExpenses->id,
                'name'            => 'حساب مصروف عمولات تصميم',
                'type'            => 'expense',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );

        // الحسابات التابعة لـ تكلفة البضاعة المباعة (42) -> مدين
        Account::updateOrCreate(
            ['code' => '4201'],
            [
                'parent_id'       => $costOfGoodsSold->id,
                'name'            => 'حساب تكلفة البضاعة والسلع المباعة للمنظومة رئيسي',
                'type'            => 'expense',
                'nature'          => 'debit',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]
        );
    }
}
