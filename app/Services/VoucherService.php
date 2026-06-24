<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Treasury;
use App\Models\Bank;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class VoucherService
{
    protected $journalService;

    public function __construct(JournalEntryService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * معالجة وحفظ سند مالي جديد (صرف أو قبض) وتوليد قيده المحاسبي فوراً مع الـ Sub-ledgers
     */
    public function createVoucher(array $data, int $userId): Voucher
    {
        return DB::transaction(function () use ($data, $userId) {
            $voucherData = array_merge($data, ['user_id' => $userId]);

            // إنشاء السند المالي وتخزين قنوات الحسابات المساعدة
            $voucher = Voucher::create($voucherData);

            // توليد القيد المحاسبي المزدوج المرتبط وعكس كود الـ ID الناتج في السند لتأمين الربط
            $journalEntry = $this->generateJournalEntry($voucher);
            $voucher->update(['journal_entry_id' => $journalEntry->id]);

            return $voucher->load(['account', 'fundAccount', 'treasury', 'bank', 'user']);
        });
    }

    /**
     * تعديل وتحديث سند مالي قائم بشكل آمن مع إزاحة القيد القديم وتحديث أرصدة الحسابات المساعدة
     */
    public function updateVoucher(Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(function () use ($voucher, $data) {
            // حذف القيد المالي القديم المباشر لتفريغ الأرصدة التراكمية في الشجرة والحسابات الفرعية
            if ($voucher->journal_entry_id) {
                $oldEntry = JournalEntry::find($voucher->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);

                    // تنظيف السطر نهائياً لتفريغ الفهرس الفريد ومنع كراش التعديل المتزامن
                    $oldEntry->forceDelete();
                }
            }

            // تحديث بيانات السند بالحسابات المساعدة الجديدة
            $voucher->update($data);

            // إعادة بناء وتوليد القيد المالي الجديد بالقيم والأرصدة المحدثة
            $newEntry = $this->generateJournalEntry($voucher);
            $voucher->update(['journal_entry_id' => $newEntry->id]);

            return $voucher->load(['account', 'fundAccount', 'treasury', 'bank', 'user']);
        });
    }

    /**
     * حذف السند مالياً وعكس أثره وقيوده من دفاتر اليومية الجارية والأستاذ المساعد بالكامل
     */
    public function deleteVoucher(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            if ($voucher->journal_entry_id) {
                $oldEntry = JournalEntry::find($voucher->journal_entry_id);
                if ($oldEntry) {
                    $this->journalService->deleteEntry($oldEntry);
                }
            }

            // الحذف الأرشيفي الآمن للمستند (Soft Delete)
            $voucher->delete();
        });
    }

    /**
     * توليد القيد المالي المزدوج التلقائي بناءً على القواعد المحاسبية الصارمة للـ Sub-ledgers (صرف أو قبض)
     */
    private function generateJournalEntry(Voucher $voucher): JournalEntry
    {
        $lines = [];

        // عزل وتحديد نوع ومعرف الحساب المساعد للنقدية (خزينة أم بنك) بناءً على طريقة السداد
        $fundSubLedgerType = null;
        $fundSubLedgerId = null;

        if ($voucher->payment_method === 'cash') {
            $fundSubLedgerType = Treasury::class;
            $fundSubLedgerId = $voucher->treasury_id;
        } else {
            $fundSubLedgerType = Bank::class;
            $fundSubLedgerId = $voucher->bank_id;
        }

        if ($voucher->voucher_type === 'payment') {
            /**
             * حالة سند الصرف (Payment Voucher):
             * 1. الطرف المدين (Debit): الحساب الرئيسي الموحد مع الـ Sub-ledger المستهدف (مورد، عميل، مصروف).
             * 2. الطرف الدائن (Credit): حساب النقدية التجميعي الرئيسي مع الـ Sub-ledger الخاص بالخزنة أو البنك الفرعي الصارف.
             */
            $lines[] = [
                'account_id'      => $voucher->account_id,
                'sub_ledger_type' => $voucher->sub_ledger_type,
                'sub_ledger_id'   => $voucher->sub_ledger_id,
                'debit'           => $voucher->amount,
                'credit'          => 0.00,
                'line_notes'      => 'أمر صرف بموجب سند مالي رقم: ' . $voucher->voucher_number . ' - ' . $voucher->notes,
            ];

            $lines[] = [
                'account_id'      => $voucher->fund_account_id,
                'sub_ledger_type' => $fundSubLedgerType,
                'sub_ledger_id'   => $fundSubLedgerId,
                'debit'           => 0.00,
                'credit'          => $voucher->amount,
                'line_notes'      => 'خروج نقدية بموجب سند صرف رقم: ' . $voucher->voucher_number,
            ];
        } else {
            /**
             * حالة سند القبض (Receipt Voucher):
             * 1. الطرف المدين (Debit): حساب النقدية التجميعي الرئيسي مع الـ Sub-ledger الخاص بالخزنة أو البنك الفرعي المستلم.
             * 2. الطرف الدائن (Credit): الحساب الرئيسي الموحد مع الـ Sub-ledger المستهدف (مورد، عميل، مصروف).
             */
            $lines[] = [
                'account_id'      => $voucher->fund_account_id,
                'sub_ledger_type' => $fundSubLedgerType,
                'sub_ledger_id'   => $fundSubLedgerId,
                'debit'           => $voucher->amount,
                'credit'          => 0.00,
                'line_notes'      => 'دخول نقدية بموجب سند قبض رقم: ' . $voucher->voucher_number,
            ];

            $lines[] = [
                'account_id'      => $voucher->account_id,
                'sub_ledger_type' => $voucher->sub_ledger_type,
                'sub_ledger_id'   => $voucher->sub_ledger_id,
                'debit'           => 0.00,
                'credit'          => $voucher->amount,
                'line_notes'      => 'تحصيل مالي بموجب سند رقم: ' . $voucher->voucher_number . ' - ' . $voucher->notes,
            ];
        }

        return $this->journalService->createEntry([
            'entry_number' => $voucher->voucher_number,
            'entry_date'   => Carbon::parse($voucher->voucher_date)->format('Y-m-d'),
            'type'         => 'journal',
            'notes'        => $voucher->notes ?? 'قيد تلقائي مزدوج ناتج عن حركة السندات المالية المطورة',
            'user_id'      => $voucher->user_id,
            'lines'        => $lines
        ]);
    }
}
