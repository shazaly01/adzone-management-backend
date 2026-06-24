<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\Api\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function __construct()
    {
        // تطبيق حماية الصلاحيات الخاصة بـ Spatie عبر الـ Policy تلقائياً
        $this->authorizeResource(Account::class, 'account');
    }

    /**
     * عرض شجرة الحسابات كاملة (المستوى الأول وما يتفرع منه)
     */
    public function index(): JsonResponse
    {
        $rootAccounts = Account::with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => AccountResource::collection($rootAccounts)
        ]);
    }

    /**
     * إنشاء حساب جديد يدوي في الشجرة (للحسابات العامة والنظامية)
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب المالي في الشجرة بنجاح.',
            'data'    => new AccountResource($account)
        ], 201);
    }

    /**
     * عرض تفاصيل حساب محدد مع حساباته التابعة مباشرة
     */
    public function show(Account $account): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new AccountResource($account->load(['parent', 'children']))
        ]);
    }

    /**
     * تحديث بيانات حساب مالي في الشجرة
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $account->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الحساب المالي بنجاح.',
            'data'    => new AccountResource($account)
        ]);
    }

    /**
     * حذف حساب مالي من الشجرة (ناعم)
     */
    public function destroy(Account $account): JsonResponse
    {
        // ميكانيكية حماية: منع حذف الحساب إذا كان لديه أبناء أو حركات مالية مسجلة
        if ($account->children()->exists() || $account->journalLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذا الحساب لارتباطه بحسابات تابعة أو قيود مالية مسجلة بالدفاتر.'
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الحساب المالي من الشجرة بنجاح.'
        ]);
    }

    /**
     * كشف حساب مخصص (محمي ومؤمن بالكامل ضد تسرب المحذوفات الناعمة)
     */
    public function statement(Account $account): JsonResponse
    {
        // الفرز على مستوى قاعدة البيانات مع فرض شرط استبعاد القيود المحذوفة ناعماً لتأمين الـ Join اليدوي
        $lines = $account->journalLines()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_entry_lines.*')
            ->whereNull('journal_entries.deleted_at') // تأمين نطاق الـ SoftDeletes ميكانيكياً
            ->orderBy('journal_entries.entry_date', 'asc')
            ->orderBy('journal_entries.id', 'asc')
            ->with(['journalEntry', 'subLedger'])
            ->get();

        $statementData = [];
        $runningBalance = (float) $account->opening_balance;

        // بناء الأسطر واحتساب الرصيد التراكمي الماشي بناءً على الطبيعة المحاسبية
        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if ($account->nature === 'debit') {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $statementData[] = [
                'entry_number'  => $line->journalEntry->entry_number,
                'date'          => $line->journalEntry->entry_date ? $line->journalEntry->entry_date->format('Y-m-d') : null,
                'type'          => $line->journalEntry->type,
                'general_notes' => $line->journalEntry->notes,
                'line_notes'    => $line->line_notes,
                'debit'         => $debit,
                'credit'        => $credit,
                'balance'       => round($runningBalance, 2),
                'sub_ledger'    => $line->subLedger ? $line->subLedger->name : null,
            ];
        }

        return response()->json([
            'success' => true,
            'account' => [
                'id'              => $account->id,
                'name'            => $account->name,
                'code'            => $account->code,
                'opening_balance' => (float) $account->opening_balance,
                'current_balance' => (float) $account->current_balance,
            ],
            'statement' => $statementData
        ]);
    }
}
