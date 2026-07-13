<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class JournalEntryService
{
    /**
     * إنشاء قيد أو سند مالي جديد مع تحديث الأرصدة الحالية والافتتاحية بأمان
     */
    public function createEntry(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء رأس القيد/السند
            $journalEntry = JournalEntry::create([
                'entry_number' => $data['entry_number'],
                'entry_date'   => $data['entry_date'],
                'type'         => $data['type'],
                'notes'        => $data['notes'] ?? null,
                'user_id'      => Auth::id() ?? ($data['user_id'] ?? null),
            ]);

            $accountChanges = [];
            $subLedgerChanges = [];

            // جلب جميع الحسابات في الذاكرة لمرة واحدة لتسريع عملية تتبع شجرة الآباء
            $allAccounts = Account::all()->keyBy('id');

            // 2. تسجيل الأسطر وتجميع الأثر المالي للحسابات العامة والمساندة
            foreach ($data['lines'] as $line) {
                $journalEntry->lines()->create([
                    'account_id'      => $line['account_id'],
                    'debit'           => $line['debit'],
                    'credit'          => $line['credit'],
                    'line_notes'      => $line['line_notes'] ?? null,
                    'sub_ledger_type' => $line['sub_ledger_type'] ?? null,
                    'sub_ledger_id'   => $line['sub_ledger_id'] ?? null,
                ]);

                // حساب الأثر المالي للحساب العام وتصعيده في الشجرة بشكل محاسبي ذكي
                $this->calculateNetEffect($line['account_id'], $line['debit'], $line['credit'], $accountChanges, $allAccounts);

                // حساب الأثر المالي للحساب المساعد (إن وجد)
                if (!empty($line['sub_ledger_type']) && !empty($line['sub_ledger_id'])) {
                    $this->calculateSubLedgerEffect($line['sub_ledger_type'], $line['sub_ledger_id'], $line['debit'], $line['credit'], $subLedgerChanges);
                }
            }

            // 3. تطبيق وتحديث الأرصدة في قاعدة البيانات
            $this->applyAccountChanges($accountChanges);
            $this->applySubLedgerChanges($subLedgerChanges, $data['type']);

            return $journalEntry;
        });
    }

    /**
     * تعديل قيد مالي مطور مع معالجة حقول الأرصدة الافتتاحية والحالية عند التراجع وإعادة البناء
     */
    public function updateEntry(JournalEntry $journalEntry, array $data): JournalEntry
    {
        if ($journalEntry->type !== 'opening_balance' && !Carbon::parse($journalEntry->entry_date)->isSameDay(Carbon::now())) {
            throw new Exception('لا يمكن تعديل القيود أو السندات المالية العائدة لأيام سابقة مباشرة. يرجى إلغاؤها بقيد عكسي.');
        }

        return DB::transaction(function () use ($journalEntry, $data) {
            $accountChanges = [];
            $subLedgerChanges = [];
            $allAccounts = Account::all()->keyBy('id');

            // 1. مسح الأثر المالي القديم كاملاً عبر عكس القيم
            foreach ($journalEntry->lines as $oldLine) {
                $this->calculateNetEffect($oldLine->account_id, -$oldLine->debit, -$oldLine->credit, $accountChanges, $allAccounts);

                if (!empty($oldLine->sub_ledger_type) && !empty($oldLine->sub_ledger_id)) {
                    $this->calculateSubLedgerEffect($oldLine->sub_ledger_type, $oldLine->sub_ledger_id, -$oldLine->debit, -$oldLine->credit, $subLedgerChanges);
                }
            }

            // التراجع عن الأثر المالي القديم في الجداول المساعدة بناءً على نوع القيد الأصلي
            $this->applySubLedgerChanges($subLedgerChanges, $journalEntry->type);

            // تصفير مصفوفة الحسابات المساعدة لاستقبال القيم الجديدة بأمان دون تداخل
            $subLedgerChanges = [];

            // 2. حذف أسطر القيد القديمة فيزيائياً من قاعدة البيانات
            $journalEntry->lines()->forceDelete();

            // 3. تحديث بيانات رأس القيد/السند
            $journalEntry->update([
                'entry_number' => $data['entry_number'],
                'entry_date'   => $data['entry_date'],
                'type'         => $data['type'],
                'notes'        => $data['notes'] ?? null,
            ]);

            // 4. تطبيق الأثر المالي الجديد وتخزين الأسطر المعدلة
            foreach ($data['lines'] as $newLine) {
                $journalEntry->lines()->create([
                    'account_id'      => $newLine['account_id'],
                    'debit'           => $newLine['debit'],
                    'credit'          => $newLine['credit'],
                    'line_notes'      => $newLine['line_notes'] ?? null,
                    'sub_ledger_type' => $newLine['sub_ledger_type'] ?? null,
                    'sub_ledger_id'   => $newLine['sub_ledger_id'] ?? null,
                ]);

                $this->calculateNetEffect($newLine['account_id'], $newLine['debit'], $newLine['credit'], $accountChanges, $allAccounts);

                if (!empty($newLine['sub_ledger_type']) && !empty($newLine['sub_ledger_id'])) {
                    $this->calculateSubLedgerEffect($newLine['sub_ledger_type'], $newLine['sub_ledger_id'], $newLine['debit'], $newLine['credit'], $subLedgerChanges);
                }
            }

            // 5. تطبيق الصافي النهائي للتحديثات في قاعدة البيانات
            $this->applyAccountChanges($accountChanges);
            $this->applySubLedgerChanges($subLedgerChanges, $data['type']);

            return $journalEntry->refresh();
        });
    }

    /**
     * حذف قيد مالي (حذف ناعم) مع عكس الأثر من الأرصدة الافتتاحية والحالية تتابعياً
     */
    public function deleteEntry(JournalEntry $journalEntry): bool
    {
        return DB::transaction(function () use ($journalEntry) {
            $accountChanges = [];
            $subLedgerChanges = [];
            $allAccounts = Account::all()->keyBy('id');

            // 1. التراجع وعكس الأثر المالي لجميع الأسطر قبل الحذف
            foreach ($journalEntry->lines as $line) {
                $this->calculateNetEffect($line->account_id, -$line->debit, -$line->credit, $accountChanges, $allAccounts);

                if (!empty($line->sub_ledger_type) && !empty($line->sub_ledger_id)) {
                    $this->calculateSubLedgerEffect($line->sub_ledger_type, $line->sub_ledger_id, -$line->debit, -$line->credit, $subLedgerChanges);
                }
            }

            // 2. تطبيق التحديثات العكسية للأرصدة
            $this->applyAccountChanges($accountChanges);
            $this->applySubLedgerChanges($subLedgerChanges, $journalEntry->type);

            // 3. تحرير الفهرس الفريد لرقم القيد لضمان إمكانية إعادة استخدام الرقم
            $journalEntry->update([
                'entry_number' => $journalEntry->entry_number . '_deleted_' . Carbon::now()->timestamp
            ]);

            // 4. تنفيذ الحذف الناعم التتابعي للأسطر ورأس المستند
            $journalEntry->lines()->delete();
            return $journalEntry->delete();
        });
    }

    /**
     * حساب الأثر المالي وتصعيده شجرياً بدقة متناهية (تغطية الحسابات العكسية Contra-Accounts)
     */
    private function calculateNetEffect(int $accountId, float $debit, float $credit, array &$changes, \Illuminate\Support\Collection $allAccounts): void
    {
        $currentId = $accountId;

        while ($currentId) {
            $account = $allAccounts->get($currentId);
            if (!$account) break;

            if ($account->nature === 'debit') {
                $nodeEffect = $debit - $credit;
            } else {
                $nodeEffect = $credit - $debit;
            }

            if (!isset($changes[$currentId])) {
                $changes[$currentId] = 0;
            }
            $changes[$currentId] += $nodeEffect;

            $currentId = $account->parent_id;
        }
    }

    /**
     * حساب وتجميع الأثر المالي المباشر في الذاكرة للدفاتر المساعدة (Sub-Ledgers)
     */
    private function calculateSubLedgerEffect(string $type, int $id, float $debit, float $credit, array &$subChanges): void
    {
        if (str_contains(strtolower($type), 'supplier')) {
            $netEffect = $credit - $debit;
        } else {
            $netEffect = $debit - $credit;
        }

        $compositeKey = "{$type}:{$id}";

        if (!isset($subChanges[$compositeKey])) {
            $subChanges[$compositeKey] = [
                'type'   => $type,
                'id'     => $id,
                'effect' => 0.00,
            ];
        }

        $subChanges[$compositeKey]['effect'] += $netEffect;
    }

    /**
     * تحديث أرصدة شجرة الحسابات العامة دفعة واحدة بطريقة تمنع الـ Deadlock
     */
    private function applyAccountChanges(array $changes): void
    {
        ksort($changes);

        foreach ($changes as $accountId => $netEffect) {
            if (round($netEffect, 4) == 0) {
                continue;
            }

            $account = Account::lockForUpdate()->find($accountId);

            if ($account) {
                $account->current_balance += $netEffect;
                $account->save();
            }
        }
    }

    /**
     * تحديث أرصدة الجداول المساعدة (نسخة معمارية نقية معزولة تعتمد على الـ FQCN الموثوق مباشرة)
     */
    private function applySubLedgerChanges(array $subChanges, string $entryType): void
    {
        ksort($subChanges);

        foreach ($subChanges as $change) {
            if (round($change['effect'], 4) == 0) {
                continue;
            }

            // الكลาส يأتي صريحاً ومطابقاً بنسبة 100% من قاعدة البيانات وبوابة التحقق بدون حاجة لـ match يدوي
            $modelClass = $change['type'];

            if (class_exists($modelClass)) {
                $subLedgerInstance = $modelClass::lockForUpdate()->find($change['id']);

                if ($subLedgerInstance) {
                    $subLedgerInstance->current_balance += $change['effect'];

                    if ($entryType === 'opening_balance') {
                        $subLedgerInstance->opening_balance += $change['effect'];
                    }

                    $subLedgerInstance->save();
                }
            }
        }
    }
}
