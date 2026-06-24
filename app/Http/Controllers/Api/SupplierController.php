<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Api\SupplierResource;
use App\Models\Supplier;
use App\Models\Account;
use App\Services\SubLedgerOpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    protected SubLedgerOpeningBalanceService $openingBalanceService;

    public function __construct(SubLedgerOpeningBalanceService $openingBalanceService)
    {
        $this->openingBalanceService = $openingBalanceService;

        // تطبيق حماية الصلاحيات التلقائية عبر الـ Policy المرتبط بالموديل
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    /**
     * عرض قائمة الموردين مع حساب الأرصدة الفعلية برمجياً
     */
    public function index(): JsonResponse
    {
        $suppliers = Supplier::with('account')
                             ->withSum('journalLines', 'debit')
                             ->withSum('journalLines', 'credit')
                             ->latest()
                             ->get();

        return response()->json([
            'success' => true,
            'data'    => SupplierResource::collection($suppliers)
        ]);
    }

    /**
     * إنشاء مورد جديد ومزامنة قيد الرصيد الافتتاحي الصامت بالتزامن
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData) {
            // جلب حساب الموردين الإجمالي الثابت من شجرة الحسابات
            $parentAccount = Account::where('code', Account::CODE_SUPPLIERS)->firstOrFail();

            // 1. إنشاء سجل المورد أولاً بقيم صفرية لتهيئة الـ ID لضمان دقة ربط العلاقات
            $supplier = Supplier::create([
                'name'            => $validatedData['name'],
                'phone'           => $validatedData['phone'] ?? null,
                'tax_number'      => $validatedData['tax_number'] ?? null,
                'account_id'      => $parentAccount->id,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
            ]);

            // 2. استدعاء السرفيس الوسيط لتوليد القيد الدائن الموازن وتحديث الأرصدة الفيزيائية
            if (isset($validatedData['opening_balance']) && (float)$validatedData['opening_balance'] > 0) {
                $this->openingBalanceService->syncOpeningBalance($supplier, (float)$validatedData['opening_balance']);
            }

            $supplier->load('account');

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء سجل المورد ومزامنة رصيده الافتتاحي بنجاح.',
                'data'    => new SupplierResource($supplier)
            ], 201);
        });
    }

    /**
     * عرض تفاصيل مورد محدد
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load('account')
                 ->loadSum('journalLines', 'debit')
                 ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'data'    => new SupplierResource($supplier)
        ]);
    }

    /**
     * تحديث بيانات المورد وإعادة صياغة قيد الرصيد الافتتاحي تلقائياً عند التعديل
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData, $supplier) {
            // 1. تحديث البيانات الأساسية للمورد
            $supplier->update([
                'name'       => $validatedData['name'],
                'phone'      => $validatedData['phone'] ?? $supplier->phone,
                'tax_number' => $validatedData['tax_number'] ?? $supplier->tax_number,
            ]);

            // 2. تحديث ومزامنة الرصيد الافتتاحي (استراتيجية مسح الأثر المحاسبي القديم وإعادة البناء)
            if (isset($validatedData['opening_balance'])) {
                $this->openingBalanceService->syncOpeningBalance($supplier, (float)$validatedData['opening_balance']);
            }

            $supplier->load('account')
                     ->loadSum('journalLines', 'debit')
                     ->loadSum('journalLines', 'credit');

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المورد بنجاح وإعادة احتساب الرصيد الافتتاحي.',
                'data'    => new SupplierResource($supplier)
            ]);
        });
    }

    /**
     * حذف سجل المورد ناعماً بعد عكس وتصفير أثر قيده الافتتاحي تماماً لسلامة الدفاتر المالية
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        return DB::transaction(function () use ($supplier) {
            // كسر وعكس الأثر المالي للقيد الصامت المرتبط بالمورد من شجرة الحسابات قبل حذف السجل
            $this->openingBalanceService->syncOpeningBalance($supplier, 0.00);

            $supplier->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف سجل المورد وعكس أثره المالي من النظام تماماً.'
            ]);
        });
    }
}
