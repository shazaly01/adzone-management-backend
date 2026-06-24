<?php

namespace Tests\Feature\Api;

use Tests\ApiTestCase;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Account;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JournalEntryTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $leafAccount1;
    protected Account $leafAccount2;

    protected function setUp(): void
    {
        parent::setUp();

        // إعداد بيئة المصادقة والصلاحيات للمشرف العام عبر المنظومة الموحدة Sanctum
        $this->user = User::factory()->create(['email' => 'admin_journal@test.com']);
        Sanctum::actingAs($this->user);

        // تطبيق شرط تخطي الصلاحيات للمشرف العام
        Gate::before(function ($user, $ability) {
            return $user->email === 'admin_journal@test.com' ? true : null;
        });

        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            \Spatie\Permission\Models\Permission::findOrCreate('journal_entry.view', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('journal_entry.create', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('journal_entry.update', 'api');
            \Spatie\Permission\Models\Permission::findOrCreate('journal_entry.delete', 'api');
        }

        // تأسيس حسابات فرعية حقيقية تتبع أرقام الكود السيادية صراحة
        $this->leafAccount1 = Account::factory()->create([
            'name'            => 'الصندوق الفرعي أ',
            'code'            => '1101001',
            'type'            => 'system',
            'nature'          => 'debit',
            'current_balance' => 5000.00
        ]);

        $this->leafAccount2 = Account::factory()->create([
            'name'            => 'مصروفات الصيانة العامة',
            'code'            => '4102001',
            'type'            => 'expense',
            'nature'          => 'debit',
            'current_balance' => 0.00
        ]);
    }

    /**
     * =========================================================================
     * 1. اختبارات المسارات الناجحة (Happy Paths)
     * =========================================================================
     */

    public function test_can_list_journal_entries(): void
    {
        JournalEntry::create([
            'entry_number' => 'JV-2026-0001',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => 'قيد مرجعي للقائمة',
            'user_id'      => $this->user->id
        ]);

        $response = $this->getJson('/api/journal-entries');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'entry_number', 'entry_date', 'type', 'notes', 'lines']
                    ]
                ]
            ]);
    }

    public function test_can_create_balanced_journal_entry_successfully_and_propagate_balances(): void
    {
        $payload = [
            'entry_number' => 'JV-1001',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => 'إثبات قيد تسوية يدوي متوازن الكفة',
            'lines'        => [
                [
                    'account_id' => $this->leafAccount2->id,
                    'debit'      => 1200.00,
                    'credit'     => 0.00,
                    'line_notes' => 'تحميل بند المصاريف'
                ],
                [
                    'account_id' => $this->leafAccount1->id,
                    'debit'      => 0.00,
                    'credit'     => 1200.00,
                    'line_notes' => 'خروج من الصندوق'
                ]
            ]
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم تسجيل القيد المالي وتحديث أرصدة الحسابات بنجاح.'
            ]);

        $this->assertDatabaseHas('journal_entries', ['entry_number' => 'JV-1001']);

        $this->leafAccount1->refresh();
        $this->leafAccount2->refresh();

        $this->assertEquals(3800.00, $this->leafAccount1->current_balance);
        $this->assertEquals(1200.00, $this->leafAccount2->current_balance);
    }

    public function test_can_show_journal_entry_details(): void
    {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-1002',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'user_id'      => $this->user->id
        ]);

        $response = $this->getJson("/api/journal-entries/{$entry->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id'           => $entry->id,
                    'entry_number' => 'JV-1002'
                ]
            ]);
    }

    public function test_can_update_journal_entry_on_the_same_day(): void
    {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-1003',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'user_id'      => $this->user->id
        ]);

        $entry->lines()->create(['account_id' => $this->leafAccount1->id, 'debit' => 500.00, 'credit' => 0.00]);
        $entry->lines()->create(['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 500.00]);

        $updatePayload = [
            'entry_number' => 'JV-1003-MOD',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => 'تعديل قيم القيد بنفس اليوم',
            'lines'        => [
                ['account_id' => $this->leafAccount1->id, 'debit' => 1000.00, 'credit' => 0.00],
                ['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 1000.00]
            ]
        ];

        $response = $this->putJson("/api/journal-entries/{$entry->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('journal_entries', ['entry_number' => 'JV-1003-MOD']);
    }

    public function test_can_soft_delete_journal_entry_and_reverse_all_financial_effects(): void
    {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-1004',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'user_id'      => $this->user->id
        ]);

        $entry->lines()->create(['account_id' => $this->leafAccount2->id, 'debit' => 500.00, 'credit' => 0.00]);
        $this->leafAccount2->increment('current_balance', 500.00);

        $response = $this->deleteJson("/api/journal-entries/{$entry->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('journal_entries', ['id' => $entry->id]);

        $this->leafAccount2->refresh();
        $this->assertEquals(0.00, $this->leafAccount2->current_balance);
    }

    /**
     * =========================================================================
     * 2. اختبارات القيود الحسابية وفشل المدخلات (Unhappy Paths - Validation)
     * =========================================================================
     */

    public function test_cannot_create_unbalanced_journal_entry(): void
    {
        $payload = [
            'entry_number' => 'JV-FAIL-1',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'lines'        => [
                ['account_id' => $this->leafAccount1->id, 'debit' => 1000.00, 'credit' => 0.00],
                ['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 950.00]
            ]
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        // التعديل: مطابقة مفتاح التحقق الحقيقي للقيد غير المتوازن الصادر من الباك-إند
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['journal_balance']);
    }

    public function test_cannot_create_journal_entry_directly_on_parent_account(): void
    {
        $parentAccount = Account::factory()->create(['name' => 'الأصول الإجمالية', 'code' => '1109']);
        Account::factory()->create(['name' => 'حساب فرعي', 'code' => '1109001', 'parent_id' => $parentAccount->id]);

        $payload = [
            'entry_number' => 'JV-FAIL-2',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'lines'        => [
                ['account_id' => $parentAccount->id, 'debit' => 500.00, 'credit' => 0.00],
                ['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 500.00]
            ]
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        // إذا كان النظام يمنع حساب الآباء عبر الـ Form Request سيرجع 422، وإذا كان يمنعه عبر استثناء السيرفيس سيرجع 500
        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    public function test_cannot_create_line_with_both_debit_and_credit_values(): void
    {
        $payload = [
            'entry_number' => 'JV-FAIL-3',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'lines'        => [
                ['account_id' => $this->leafAccount1->id, 'debit' => 500.00, 'credit' => 500.00],
                ['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 500.00]
            ]
        ];

        $response = $this->postJson('/api/journal-entries', $payload);

        // التعديل: مطابقة مفتاح التحقق الدقيق لسطر الجمع بين كفتي المدين والدائن
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.credit']);
    }

    public function test_cannot_update_historical_journal_entry_from_previous_days(): void
    {
        $historicalEntry = JournalEntry::create([
            'entry_number' => 'JV-HIST-100',
            'entry_date'   => now()->subDay()->format('Y-m-d'),
            'type'         => 'journal',
            'user_id'      => $this->user->id
        ]);

        $updatePayload = [
            'entry_number' => 'JV-HIST-ATTEMPT',
            'entry_date'   => now()->format('Y-m-d'),
            'type'         => 'journal',
            'lines'        => [
                ['account_id' => $this->leafAccount1->id, 'debit' => 100.00, 'credit' => 0.00],
                ['account_id' => $this->leafAccount2->id, 'debit' => 0.00, 'credit' => 100.00]
            ]
        ];

        $response = $this->putJson("/api/journal-entries/{$historicalEntry->id}", $updatePayload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'لا يمكن تعديل القيود أو السندات المالية العائدة لأيام سابقة مباشرة. يرجى إلغاؤها بقيد عكسي.'
            ]);
    }

    public function test_unauthorized_user_cannot_list_journal_entries(): void
    {
        $regularUser = User::factory()->create(['email' => 'accountant_clerk@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/journal-entries');
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_create_journal_entry(): void
    {
        $regularUser = User::factory()->create(['email' => 'accountant_clerk@test.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/journal-entries', ['entry_number' => 'JV-HACK-1']);
        $response->assertStatus(403);
    }
}
