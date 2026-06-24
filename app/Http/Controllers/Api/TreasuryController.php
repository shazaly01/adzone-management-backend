<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\StoreTreasuryRequest;
use App\Http\Requests\Treasury\UpdateTreasuryRequest;
use App\Http\Resources\Api\TreasuryResource;
use App\Models\Treasury;
use App\Models\Account;
use App\Services\SubLedgerOpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TreasuryController extends Controller
{
    protected SubLedgerOpeningBalanceService $openingBalanceService;

    public function __construct(SubLedgerOpeningBalanceService $openingBalanceService)
    {
        $this->openingBalanceService = $openingBalanceService;

        // تطبيق حماية الصلاحيات عبر الـ Policy تلقائياً عبر Spatie
        $this->authorizeResource(Treasury::class, 'treasury');
    }

    /**
     * عرض قائمة الخزائن مع حساب الأرصدة الفعلية برمجياً
     */
    public function index(): JsonResponse
    {
        $treasuries = Treasury::with('account')
            ->withSum('journalLines', 'debit')
            ->withSum('journalLines', 'credit')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => TreasuryResource::collection($treasuries)
        ]);
    }

    /**
     * إنشاء خزينة جديدة وتوليد قيد الرصيد الافتتاحي الصامت بالتزامن
     */
    public function store(StoreTreasuryRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData) {
            // جلب حساب الخزائن الرئيسي من الشجرة
            $parentAccount = Account::where('code', Account::CODE_TREASURY)->firstOrFail();

            // 1. إنشاء سجل الخزينة بقيم صفرية لتهيئة الـ ID وضمان عدم التضاعف المالي
            $treasury = Treasury::create([
                'name'            => $validatedData['name'],
                'account_id'      => $parentAccount->id,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active'       => true,
            ]);

            // 2. استدعاء السرفيس الوسيط لتوليد القيد الصامت الموازن وتحديث الأرصدة الفيزيائية
            if (isset($validatedData['opening_balance']) && (float)$validatedData['opening_balance'] > 0) {
                $this->openingBalanceService->syncOpeningBalance($treasury, (float)$validatedData['opening_balance']);
            }

            $treasury->load('account');

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الخزينة الماليّة ومزامنة رصيدها الافتتاحي بنجاح.',
                'data'    => new TreasuryResource($treasury)
            ], 201);
        });
    }

    /**
     * عرض تفاصيل خزينة محددة
     */
    public function show(Treasury $treasury): JsonResponse
    {
        $treasury->load('account')
                 ->loadSum('journalLines', 'debit')
                 ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'data'    => new TreasuryResource($treasury)
        ]);
    }

    /**
     * تحديث بيانات الخزينة وإعادة صياغة القيد الافتتاحي تلقائياً عند التعديل
     */
    public function update(UpdateTreasuryRequest $request, Treasury $treasury): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData, $treasury) {
            // 1. تحديث البيانات الأساسية للخزينة
            $treasury->update([
                'name' => $validatedData['name'],
            ]);

            // 2. تحديث الرصيد الافتتاحي المضمن (عكس القديم وإعادة بناء الجديد عبر السرفيس)
            if (isset($validatedData['opening_balance'])) {
                $this->openingBalanceService->syncOpeningBalance($treasury, (float)$validatedData['opening_balance']);
            }

            $treasury->load('account')
                 ->loadSum('journalLines', 'debit')
                 ->loadSum('journalLines', 'credit');

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الخزينة بنجاح وإعادة احتساب الرصيد الافتتاحي.',
                'data'    => new TreasuryResource($treasury)
            ]);
        });
    }

    /**
     * حذف سجل الخزينة ناعماً مع تصفير وعكس أثر القيد الافتتاحي تماماً من الدفاتر
     */
    public function destroy(Treasury $treasury): JsonResponse
    {
        return DB::transaction(function () use ($treasury) {
            // عكس الأثر المالي للقيد الصامت المرتبط بالخزينة من شجرة الحسابات قبل حذف السجل
            $this->openingBalanceService->syncOpeningBalance($treasury, 0.00);

            $treasury->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف سجل الخزينة وعكس أثره المالي من النظام تماماً.'
            ]);
        });
    }
}
