<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialDashboardService
{
    /**
     * المحرك الرئيسي المحصن معمارياً لجلب تقارير المدير المالية الشاملة
     */
    public function getFinancialStats(array $filters): array
    {
        $fromDate = isset($filters['from_date'])
            ? Carbon::parse($filters['from_date'])->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $toDate = isset($filters['to_date'])
            ? Carbon::parse($filters['to_date'])->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        // استخراج طوابع الـ Morph الديناميكية للمشروع لحل مشكلة الـ Morph Map نهائياً
        $userMorphType = (new User())->getMorphClass();
        $expenseMorphType = (new Expense())->getMorphClass();

        $expenseReport = $this->getDetailedExpensesReport($fromDate, $toDate, $expenseMorphType);
        $customerReport = $this->getDetailedCustomersDebtReport();
        $designerReport = $this->getDetailedDesignersLedgerReport($toDate, $userMorphType);

        return [
            'expenses_data' => [
                'list'        => $expenseReport['list'],
                'grand_total' => (float) $expenseReport['grand_total'],
            ],
            'customers_data' => [
                'list'        => $customerReport['list'],
                'grand_total' => (float) $customerReport['grand_total'],
            ],
            'designers_data' => [
                'list'        => $designerReport['list'],
                'grand_total' => (float) $designerReport['grand_total'],
            ]
        ];
    }

    /**
     * تقرير المصروفات التفصيلي والمحصن من قنبلة الـ Morph Map وتداخل صيغ التاريخ مع جلب البيان والحساب المساعد
     */
    private function getDetailedExpensesReport(Carbon $from, Carbon $to, string $expenseMorphType): array
    {
        $expenses = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('expenses', function ($join) use ($expenseMorphType) {
                $join->on('journal_entry_lines.sub_ledger_id', '=', 'expenses.id')
                     ->where(function ($q) use ($expenseMorphType) {
                         // دعم مزدوج وبند ذكي يطابق الفئة سواء بالاسم الكامل أو الاسم المستعار للمورف
                         $q->where('journal_entry_lines.sub_ledger_type', '=', $expenseMorphType)
                           ->orWhere('journal_entry_lines.sub_ledger_type', '=', 'App\Models\Expense');
                     })
                     ->whereNull('expenses.deleted_at');
            })
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('journal_entry_lines.deleted_at')
            ->where('accounts.code', Account::CODE_EXPENSES)
            ->whereBetween('journal_entries.entry_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->select([
                'expenses.id as expense_id',
                'expenses.name as sub_ledger_name',
                'accounts.name as account_name',
                'journal_entry_lines.line_notes',
                'journal_entries.entry_number',
                DB::raw("SUM(journal_entry_lines.debit - journal_entry_lines.credit) as total_spent")
            ])
            ->groupBy(
                'expenses.id',
                'expenses.name',
                'accounts.name',
                'journal_entry_lines.line_notes',
                'journal_entries.entry_number'
            )
            ->orderByDesc('total_spent')
            ->get()
            ->map(function ($item) {
                // التحديث المختصر: نختار الحقل الأكثر دقة مباشرة لمنع التكرار والكلام الزائد
                // 1. إذا وجد بيان السطر المكتوب نكتفي به، 2. وإلا اسم المصروف الفرعي، 3. وإلا حساب الشجرة الرئيسي
                $shortName = $item->line_notes ?: ($item->sub_ledger_name ?: $item->account_name);

                return [
                    'expense_id'   => $item->expense_id,
                    'expense_name' => $shortName,
                    'total_spent'  => (float) $item->total_spent,
                ];
            })
            ->toArray();

        $grandTotal = array_sum(array_column($expenses, 'total_spent'));

        return [
            'list'        => $expenses,
            'grand_total' => $grandTotal
        ];
    }

    /**
     * تقرير مديونيات العملاء الفعليين اللحظي (ثابت ومستقل عن الفلتر الزمني وفقاً لمتطلبات لقطة الرصيد الحية)
     */
    private function getDetailedCustomersDebtReport(): array
    {
        $customers = Customer::whereNull('deleted_at')
            ->where('current_balance', '>', 0.00)
            ->orderByDesc('current_balance')
            ->select(['id', 'name', 'current_balance'])
            ->get()
            ->map(function ($customer) {
                return [
                    'customer_id'     => $customer->id,
                    'customer_name'   => $customer->name,
                    'current_balance' => (float) $customer->current_balance,
                ];
            })
            ->toArray();

        $grandTotal = array_sum(array_column($customers, 'current_balance'));

        return [
            'list'        => $customers,
            'grand_total' => $grandTotal
        ];
    }

    /**
     * تقرير مستحقات المصممين المحصن كلياً من الاختفاء العشوائي وقنبلة الـ Morph Map المحاسبية
     */
    private function getDetailedDesignersLedgerReport(Carbon $upToDate, string $userMorphType): array
    {
        $designerBalancesSubQuery = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('journal_entry_lines.deleted_at')
            ->where('accounts.code', Account::CODE_DESIGNERS_LEDGER)
            ->where(function ($q) use ($userMorphType) {
                $q->where('journal_entry_lines.sub_ledger_type', '=', $userMorphType)
                  ->orWhere('journal_entry_lines.sub_ledger_type', '=', 'App\Models\User');
            })
            ->where('journal_entries.entry_date', '<=', $upToDate->format('Y-m-d'))
            ->select([
                'journal_entry_lines.sub_ledger_id as designer_id',
                DB::raw("SUM(journal_entry_lines.credit - journal_entry_lines.debit) as balance")
            ])
            ->groupBy('journal_entry_lines.sub_ledger_id');

        $designers = DB::table('users')
            ->leftJoinSub($designerBalancesSubQuery, 'balances', function ($join) {
                $join->on('users.id', '=', 'balances.designer_id');
            })
            ->where('users.type', 'designer')
            ->whereNull('users.deleted_at')
            ->select([
                'users.id as designer_id',
                'users.full_name as designer_name',
                DB::raw("COALESCE(balances.balance, 0.00) as total_due")
            ])
            ->orderByDesc('total_due')
            ->get()
            ->map(function ($item) {
                return [
                    'designer_id'   => $item->designer_id,
                    'designer_name' => $item->designer_name,
                    'total_due'     => (float) $item->total_due,
                ];
            })
            ->toArray();

        $grandTotal = array_sum(array_column($designers, 'total_due'));

        return [
            'list'        => $designers,
            'grand_total' => $grandTotal
        ];
    }
}
