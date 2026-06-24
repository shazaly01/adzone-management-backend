<?php

namespace Tests\Feature\Api\Reports;

use Tests\ApiTestCase;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class FinancialReportTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $assetAccount;
    protected Account $liabilityAccount;
    protected Account $revenueAccount;
    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة وتخطي الصلاحيات للمشرف العام
        $this->user = User::factory()->create(['email' => 'admin_fin_report@test.com']);
        Sanctum::actingAs($this->user);

        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_fin_report@test.com' ? true : null;
        });

        // 2. بناء الهيكل الأساسي لشجرة الحسابات السيادية (الأكواد تتبع الأنظمة القياسية)
        $this->assetAccount     = Account::factory()->create(['name' => 'الصندوق الرئيسي', 'code' => '110101', 'type' => 'system', 'parent_id' => null]);
        $this->liabilityAccount = Account::factory()->create(['name' => 'الموردين - شركة البركة', 'code' => '210101', 'type' => 'system', 'parent_id' => null]);
        $this->revenueAccount   = Account::factory()->create(['name' => 'إيرادات المبيعات الافتراضية', 'code' => '310101', 'type' => 'income', 'parent_id' => null]);
        $this->expenseAccount   = Account::factory()->create(['name' => 'مصروفات الدعاية والإعلان', 'code' => '410101', 'type' => 'expense', 'parent_id' => null]);

        // 3. ضخ قيد محاسبي تاريخي (قيد افتتاحي متوازن بقيمة 10,000) لبناء حركات مالية حقيقية
        $entry1 = JournalEntry::create([
            'entry_number' => 'JV-2026-001',
            'entry_date'   => Carbon::now()->subDays(5)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => 'قيد إثبات رأس المال الافتتاحي في الصندوق',
            'user_id'      => $this->user->id
        ]);

        // طرف مدين: الصندوق الرئيسي
        JournalEntryLine::create([
            'journal_entry_id' => $entry1->id,
            'account_id'       => $this->assetAccount->id,
            'debit'            => 10000.00,
            'credit'           => 0.00,
            'line_notes'       => 'تغذية حساب الصندوق'
        ]);

        // طرف دائن: حساب الكيان المساعد (مورد أو دائنون)
        JournalEntryLine::create([
            'journal_entry_id' => $entry1->id,
            'account_id'       => $this->liabilityAccount->id,
            'debit'            => 0.00,
            'credit'           => 10000.00,
            'sub_ledger_type'  => 'App\Models\Supplier',
            'sub_ledger_id'    => 99, // محاكاة معرف مورد معين
            'line_notes'       => 'إثبات مستحقات المورد المساعد'
        ]);

        // 4. ضخ قيد حركات جارية للفترة الحالية (مبيعات ومصروفات بقيمة 2,500)
        $entry2 = JournalEntry::create([
            'entry_number' => 'JV-2026-002',
            'entry_date'   => Carbon::now()->format('Y-m-d'),
            'type'         => 'journal',
            'notes' => 'قيد حركات المبيعات والمصروفات اليومية الجارية',
            'user_id'      => $this->user->id
        ]);

        // زيادة الصندوق بالمدين بقيمة 2,500 نتيجة إيرادات المبيعات الدائنة
        JournalEntryLine::create([
            'journal_entry_id' => $entry2->id,
            'account_id'       => $this->assetAccount->id,
            'debit'            => 2500.00,
            'credit'           => 0.00,
            'line_notes'       => 'مقبوضات نقدية من مبيعات'
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry2->id,
            'account_id'       => $this->revenueAccount->id,
            'debit'            => 0.00,
            'credit'           => 2500.00,
            'line_notes'       => 'إثبات قيمة إيراد المبيعات المحققة'
        ]);
    }

    /**
     * 1. اختبار تقرير كشف الحساب التفصيلي مع فحص طبيعة الحساب التراكمية
     */
    public function test_financial_account_ledger_report(): void
    {
        $response = $this->getJson('/api/reports/financial/account-ledger?account_id=' . $this->assetAccount->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'account_id'      => $this->assetAccount->id,
                    'account_code'    => '110101',
                    'nature'          => 'debit',
                    'closing_balance' => 12500.00 // 10000 افتتاحي + 2500 حركة جارية
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'entry_id',
                        'entry_number',
                        'entry_date',
                        'entry_type',
                        'line_notes',
                        'debit',
                        'credit',
                        'running_balance'
                    ]
                ]
            ]);

        // التأكد من صحة تتابع الرصيد التراكمي للسطور في كشف حساب الصندوق المدين
        $this->assertEquals(10000.00, $response->json('data.0.running_balance'));
        $this->assertEquals(12500.00, $response->json('data.1.running_balance'));
    }

    /**
     * 2. اختبار كشف الحساب المساعد للكيانات الفرعية عبر علاقة الـ Morph
     */
    public function test_financial_sub_ledger_statement_report(): void
    {
        $response = $this->getJson('/api/reports/financial/sub-ledger?sub_ledger_type=App\Models\Supplier&sub_ledger_id=99');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'sub_ledger_type' => 'App\Models\Supplier',
                    'sub_ledger_id'   => 99,
                    'closing_balance' => 10000.00 // المورد دائن بطبيعته
                ]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(10000.00, $response->json('data.0.credit'));
    }

    /**
     * 3. اختبار تقرير ميزان المراجعة والتحقق من توازن كفتي الحركات الإجمالية
     */
    public function test_financial_trial_balance_report(): void
    {
        $response = $this->getJson('/api/reports/financial/trial-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'totals' => [
                    'is_balanced' => true // توازن كفتي القيود الإجمالية (المدين والدائن متطابقان)
                ]
            ])
            ->assertJsonStructure([
                'totals' => ['total_period_debit', 'total_period_credit', 'is_balanced'],
                'data'   => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'parent_id',
                        'is_parent',
                        'nature',
                        'opening_balance',
                        'period_debit',
                        'period_credit',
                        'closing_balance'
                    ]
                ]
            ]);
    }

    /**
     * 4. اختبار قائمة الدخل والاحتساب التلقائي لصافي أرباح وخسائر النشاط الجاري
     */
    public function test_financial_income_statement_report(): void
    {
        $response = $this->getJson('/api/reports/financial/income-statement');

        $response->assertStatus(200)
            ->assertJson([
                'success'        => true,
                'total_revenues' => 2500.00,
                'total_expenses' => 0.00,
                'net_profit'     => 2500.00, // 2500 إيرادات - 0 مصروفات
                'outcome_type'   => 'profit'
            ]);
    }

    /**
     * 5. اختبار الميزانية العمومية والتحقق الحتمي من توازن الأصول مع الخصوم وحقوق الملكية
     */
    public function test_financial_balance_sheet_report(): void
    {
        $response = $this->getJson('/api/reports/financial/balance-sheet');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'assets' => [
                    'total_assets' => 12500.00 // إجمالي الصندوق مدين
                ],
                'liabilities_and_equity' => [
                    'current_period_net_profit'    => 2500.00, // حقن أرباح النشاط الجاري حركياً للتوازن
                    'total_liabilities_and_equity' => 12500.00 // 10000 حساب سيادي + 2500 أرباح
                ],
                'is_perfectly_balanced' => true // توازن حتمي لمعادلة المركز المالي
            ]);
    }
}
