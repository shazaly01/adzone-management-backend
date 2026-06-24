<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SubLedgerOpeningBalanceService
{
    protected JournalEntryService $journalEntryService;

    public function __construct(JournalEntryService $journalEntryService)
    {
        $this->journalEntryService = $journalEntryService;
    }

    /**
     * المزامنة الذكية للرصيد الافتتاحي المضمن للحساب المساعد مع حسابات رأس المال الحقيقية مباشرة
     */
    public function syncOpeningBalance(Model $subLedger, float $amount): void
    {
        $subLedgerBaseName = class_basename($subLedger);
        $entryNumber = "OP-" . strtoupper($subLedgerBaseName) . "-{$subLedger->id}";

        // البحث عن القيد الافتتاحي القديم المرتبط بالكيان المساعد إن وجد
        $oldEntry = JournalEntry::where('entry_number', $entryNumber)->first();

        // 1. حالة التصفير: عند تعديل الرصيد الافتتاحي إلى صفر، يتم حذف القيد وعكس أثره فوراً
        if (round($amount, 2) == 0) {
            if ($oldEntry) {
                $this->journalEntryService->deleteEntry($oldEntry);
            }
            return;
        }

        // 2. التوجيه الذكي المباشر لحساب رأس المال المتوافق مع طبيعة الكيان المساعد بدون حساب وسيط
        $contraCode = Account::CODE_PAID_CAPITAL; // الافتراضي للحسابات النقدية والتجارية (2201)

        // إذا كان الكيان المساعد مستقبلاً مخزناً أو صنفاً، يوجه لحساب رأس مال الأصول والمخزون (2202)
        if (in_array(strtolower($subLedgerBaseName), ['item', 'store', 'stockadjustment'])) {
            $contraCode = Account::CODE_ASSET_CAPITAL;
        }

        // جلب حساب رأس المال المستهدف مباشرة من قاعدة البيانات (مبذّر مسبقاً ويستحيل حذفه)
        $contraAccount = Account::where('code', $contraCode)->firstOrFail();

        // 3. بناء هيكل خطوط القيد المزدوج المتوازن تلقائياً
        $lines = [];
        $isSupplier = str_contains(strtolower(get_class($subLedger)), 'supplier');

        if (!$isSupplier) {
            // الكيانات المدينة (بنك، عميل، خزينة): الحساب المساعد مدين، وحساب رأس المال دائن
            $lines[] = [
                'account_id'      => $subLedger->account_id,
                'debit'           => $amount,
                'credit'          => 0.00,
                'line_notes'      => "رصيد افتتاحى تلقائى مضمن للكيان: {$subLedger->name}",
                'sub_ledger_type' => get_class($subLedger),
                'sub_ledger_id'   => $subLedger->id,
            ];

            $lines[] = [
                'account_id'      => $contraAccount->id,
                'debit'           => 0.00,
                'credit'          => $amount,
                'line_notes'      => "الطرف الدائن المقابل المقفل فى حساب: {$contraAccount->name}",
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
            ];
        } else {
            // الكيانات الدائنة (الموردين): الحساب المساعد دائن، وحساب رأس المال مدين
            $lines[] = [
                'account_id'      => $subLedger->account_id,
                'debit'           => 0.00,
                'credit'          => $amount,
                'line_notes'      => "رصيد افتتاحى تلقائى مضمن للمورد: {$subLedger->name}",
                'sub_ledger_type' => get_class($subLedger),
                'sub_ledger_id'   => $subLedger->id,
            ];

            $lines[] = [
                'account_id'      => $contraAccount->id,
                'debit'           => $amount,
                'credit'          => 0.00,
                'line_notes'      => "الطرف المدين المقابل المقفل فى حساب: {$contraAccount->name}",
                'sub_ledger_type' => null,
                'sub_ledger_id'   => null,
            ];
        }

        // تجهيز مصفوفة البيانات الكاملة (Payload) المتوافقة مع سيرفيس القيود
        $payload = [
            'entry_number' => $entryNumber,
            'entry_date'   => Carbon::now()->startOfYear()->format('Y-m-d'), // القيد يسجل دائماً أول السنة المالية
            'type'         => 'opening_balance',
            'notes'        => "قيد صامت ومولد تلقائياً للأرصدة الافتتاحية المضمنة مقفل فى حساب: {$contraAccount->name}",
            'lines'        => $lines,
        ];

        // 4. تنفيذ العملية بأمان: التعديل وإعادة الحساب إذا كان موجوداً، أو الإنشاء لأول مرة
        if ($oldEntry) {
            $this->journalEntryService->updateEntry($oldEntry, $payload);
        } else {
            $this->journalEntryService->createEntry($payload);
        }
    }
}
