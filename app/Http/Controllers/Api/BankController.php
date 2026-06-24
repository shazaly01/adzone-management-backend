<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\StoreBankRequest;
use App\Http\Requests\Bank\UpdateBankRequest;
use App\Http\Resources\Api\BankResource;
use App\Models\Bank;
use App\Models\Account;
use App\Services\SubLedgerOpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    protected SubLedgerOpeningBalanceService $openingBalanceService;

    public function __construct(SubLedgerOpeningBalanceService $openingBalanceService)
    {
        $this->openingBalanceService = $openingBalanceService;

        // تطبيق حماية الصلاحيات الخاصة بـ Spatie عبر الـ Policy تلقائياً
        $this->authorizeResource(Bank::class, 'bank');
    }

    /**
     * عرض قائمة الحسابات البنكية مع الحساب التجميعي وإجمالي الحركات
     */
    public function index(): JsonResponse
    {
        $banks = Bank::with('account')
            ->withSum('journalLines', 'debit')
            ->withSum('journalLines', 'credit')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => BankResource::collection($banks)
        ]);
    }

    /**
     * إنشاء حساب بنكي جديد مع توليد قيد الرصيد الافتتاحي الصامت بالتزامن
     */
    public function store(StoreBankRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData) {
            $parentAccount = Account::where('code', Account::CODE_BANKS)->firstOrFail();

            // 1. إنشاء سجل البنك أولاً بقيم صفرية لتهيئة المعرف الفريد (ID) منعاً للتضاعف المالي
            $bank = Bank::create([
                'name'            => $validatedData['name'],
                'account_number'  => $validatedData['account_number'] ?? null,
                'iban'            => $validatedData['iban'] ?? null,
                'account_id'      => $parentAccount->id,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active'       => true,
            ]);

            // 2. استدعاء السرفيس الوسيط لتوليد القيد المزدوج المتوازن وتحديث الأرصدة الفيزيائية
            if (isset($validatedData['opening_balance']) && (float)$validatedData['opening_balance'] > 0) {
                $this->openingBalanceService->syncOpeningBalance($bank, (float)$validatedData['opening_balance']);
            }

            $bank->load('account');

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء حساب البنك ومزامنة رصيده الافتتاحي بنجاح.',
                'data'    => new BankResource($bank)
            ], 201);
        });
    }

    /**
     * عرض تفاصيل بنك محدد
     */
    public function show(Bank $bank): JsonResponse
    {
        $bank->load('account')
             ->loadSum('journalLines', 'debit')
             ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'data'    => new BankResource($bank)
        ]);
    }

    /**
     * تحديث بيانات البنك وإعادة صياغة وموازنة قيد الرصيد الافتتاحي تلقائياً
     */
    public function update(UpdateBankRequest $request, Bank $bank): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData, $bank) {
            // 1. تحديث البيانات النصية والأساسية للبنك
            $bank->update([
                'name'           => $validatedData['name'],
                'account_number' => $validatedData['account_number'] ?? $bank->account_number,
                'iban'           => $validatedData['iban'] ?? $bank->iban,
            ]);

            // 2. تحديث ومزامنة الرصيد الافتتاحي (استراتيجية مسح الأثر القديم وإعادة البناء داخل السرفيس)
            if (isset($validatedData['opening_balance'])) {
                $this->openingBalanceService->syncOpeningBalance($bank, (float)$validatedData['opening_balance']);
            }

            $bank->load('account')
                 ->loadSum('journalLines', 'debit')
                 ->loadSum('journalLines', 'credit');

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات البنك وإعادة احتساب الرصيد الافتتاحي بنجاح.',
                'data'    => new BankResource($bank)
            ]);
        });
    }

    /**
     * حذف حساب بنكي (ناعم) مع تصفير وعكس الأثر المالي لقيده الافتتاحي من الدفاتر تماماً قبل الحذف
     */
    public function destroy(Bank $bank): JsonResponse
    {
        return DB::transaction(function () use ($bank) {
            // كسر وعكس الأثر المالي للقيد الصامت المرتبط بالبنك من الشجرة قبل حذف السجل لضمان سلامة الدفاتر
            $this->openingBalanceService->syncOpeningBalance($bank, 0.00);

            $bank->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف سجل البنك وعكس كافة أثاره المالية من النظام بنجاح.'
            ]);
        });
    }
}
