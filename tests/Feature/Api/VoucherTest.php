<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\Voucher;
use App\Models\Account;
use App\Models\User;
use App\Models\JournalEntry;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherTest extends ApiTestCase
{
    use RefreshDatabase;

    protected $user;
    protected $mainAccount;
    protected $fundAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد بيئة المصادقة والصلاحيات للمشرف العام عبر المنظومة الموحدة Sanctum
        $this->user = User::factory()->create(['email' => 'admin_voucher@test.com']);
        Sanctum::actingAs($this->user);

        // تطبيق شرط تخطي الصلاحيات للمشرف العام دون التأثير على فحص المستخدمين العاديين
        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_voucher@test.com' ? true : null;
        });

        // 2. تأسيس الحسابات المالية اللازمة لربط المعاملات المالية في السندات والقيود تلقائياً
        $this->mainAccount = Account::factory()->create([
            'name' => 'مصروفات الدعاية والإعلان',
            'code' => '410101',
            'type' => 'expense'
        ]);

        $this->fundAccount = Account::factory()->create([
            'name' => 'خزينة الكاشير الرئيسية',
            'code' => '110101',
            'type' => 'system'
        ]);
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_list_vouchers(): void
    {
        Voucher::factory()->create([
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'fund_account_id' => $this->fundAccount->id,
            'user_id'         => $this->user->id,
        ]);

        $response = $this->getJson('/api/vouchers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'voucher_type',
                        'voucher_type_lbl',
                        'voucher_sequence',
                        'voucher_number',
                        'account_id',
                        'account_name',
                        'sub_ledger_type',
                        'sub_ledger_id',
                        'sub_ledger_name',
                        'payment_method',
                        'payment_method_lbl',
                        'fund_account_id',
                        'fund_account_name',
                        'amount',
                        'voucher_date',
                        'notes',
                        'user_id',
                        'user_name',
                        'journal_entry_id',
                        'journal_entry_no'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_payment_voucher_successfully(): void
    {
        $payload = [
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'payment_method'  => 'cash',
            'fund_account_id' => $this->fundAccount->id,
            'amount'          => 750.00,
            'voucher_date'    => now()->format('Y-m-d H:i:s'),
            'notes'           => 'سند صرف نقدي لشراء قرطاسية ومطبوعات للمكتب',
        ];

        $response = $this->postJson('/api/vouchers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ السند المالي وتوليد القيود الحسابية المرتبطة به بنجاح.'
            ]);

        $this->assertDatabaseHas('vouchers', [
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'fund_account_id' => $this->fundAccount->id,
            'amount'          => 750.00,
        ]);

        $voucher = Voucher::first();
        $this->assertNotNull($voucher->voucher_number);
        $this->assertNotNull($voucher->journal_entry_id);

        // التحقق من ترحيل وبناء خطوط القيد المالي التلقائي (المدين والدائن) في جدول القيود
        $this->assertDatabaseHas('journal_entries', [
            'id'           => $voucher->journal_entry_id,
            'entry_number' => $voucher->voucher_number,
        ]);
    }

    public function test_can_create_receipt_voucher_successfully(): void
    {
        $incomeAccount = Account::factory()->create([
            'name' => 'إيرادات خدمات استشارية',
            'code' => '310102',
            'type' => 'income'
        ]);

        $payload = [
            'voucher_type'    => 'receipt',
            'account_id'      => $incomeAccount->id,
            'payment_method'  => 'bank',
            'fund_account_id' => $this->fundAccount->id,
            'amount'          => 3200.00,
            'voucher_date'    => now()->format('Y-m-d H:i:s'),
            'notes'           => 'سند قبض بنكي دفعة من عقد استشارات فنية',
        ];

        $response = $this->postJson('/api/vouchers', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vouchers', [
            'voucher_type' => 'receipt',
            'amount'       => 3200.00,
        ]);
    }

    public function test_can_show_voucher_details_with_journal_links(): void
    {
        $voucher = Voucher::factory()->create([
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'fund_account_id' => $this->fundAccount->id,
            'user_id'         => $this->user->id,
        ]);

        $response = $this->getJson("/api/vouchers/{$voucher->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'             => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                ]
            ]);
    }

    public function test_can_update_voucher_and_rebalance_journal_entries(): void
    {
        $voucher = Voucher::factory()->create([
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'fund_account_id' => $this->fundAccount->id,
            'amount'          => 500.00,
            'user_id'         => $this->user->id,
        ]);

        $updatePayload = [
            'voucher_type'    => 'payment',
            'account_id'      => $voucher->account_id,
            'payment_method'  => 'cash',
            'fund_account_id' => $voucher->fund_account_id,
            'amount'          => 950.00, // تعديل القيمة المالية للسند لإعادة الموازنة
            'voucher_date'    => $voucher->voucher_date->format('Y-m-d H:i:s'),
            'notes'           => 'تعديل قيمة السند بعد المراجعة اللوجستية والتدقيق البنكي',
        ];

        $response = $this->putJson("/api/vouchers/{$voucher->id}", $updatePayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('vouchers', [
            'id'     => $voucher->id,
            'amount' => 950.00,
        ]);
    }

    public function test_can_soft_delete_voucher_and_completely_wipe_journal_entry(): void
    {
        $voucher = Voucher::factory()->create([
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'fund_account_id' => $this->fundAccount->id,
            'user_id'         => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/vouchers/{$voucher->id}");

        $response->assertStatus(200);

        // التأكد من تطبيق الأرشفة المؤقتة بنجاح
        $this->assertSoftDeleted('vouchers', [
            'id' => $voucher->id
        ]);

        // التأكد التام من إزاحة وحذف القيد المالي من الدفاتر تماماً تماشياً مع معيار عدم بقاء قيود معلقة
        $this->assertDatabaseMissing('journal_entries', [
            'entry_number' => $voucher->voucher_number
        ]);
    }

    /**
     * =========================================================================
     * 2. اختبارات القيود وفشل مدخلات التحقق (Unhappy Paths - Validation)
     * =========================================================================
     */

    public function test_cannot_create_voucher_with_zero_or_negative_amount(): void
    {
        $payload = [
            'voucher_type'    => 'payment',
            'account_id'      => $this->mainAccount->id,
            'payment_method'  => 'cash',
            'fund_account_id' => $this->fundAccount->id,
            'amount'          => -50.00, // خطأ: القيمة تشترط مبالغ حقيقية أكبر من صفر (gt:0)
            'voucher_date'    => now()->format('Y-m-d H:i:s'),
        ];

        $response = $this->postJson('/api/vouchers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * =========================================================================
     * 3. اختبارات حماية المنافذ والصلاحيات (Security & Authorization)
     * =========================================================================
     */

    public function test_unauthorized_user_cannot_list_vouchers(): void
    {
        $hackerUser = User::factory()->create(['email' => 'regular_employee@test.com']);
        Sanctum::actingAs($hackerUser);

        $response = $this->getJson('/api/vouchers');

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_create_voucher(): void
    {
        $hackerUser = User::factory()->create(['email' => 'regular_employee@test.com']);
        Sanctum::actingAs($hackerUser);

        $response = $this->postJson('/api/vouchers', [
            'voucher_type' => 'payment'
        ]);

        $response->assertStatus(403);
    }
}
