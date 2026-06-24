<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreWarehouseRequest;
use App\Http\Requests\Store\UpdateWarehouseRequest;
use App\Http\Resources\Api\StoreResource;
use App\Models\Store;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct()
    {
        // تطبيق حماية الصلاحيات المعتمدة على Spatie عبر الـ Policy تلقائياً
        $this->authorizeResource(Store::class, 'store');
    }

    /**
     * عرض قائمة المخازن والمستودعات مع التقييم المالي الفعلي (الرصيد المالي)
     */
    public function index(): JsonResponse
    {
        // [تعديل]: تم إضافة with('account') لجلب بيانات حساب المخزون الرئيسي الثابت (1104) في جدول العرض
        $stores = Store::with('account')
                       ->withSum('journalLines', 'debit')
                       ->withSum('journalLines', 'credit')
                       ->latest()
                       ->get();

        return response()->json([
            'success' => true,
            'data'    => StoreResource::collection($stores)
        ]);
    }

    /**
     * إنشاء مخزن جديد وربطه بحساب المخزون الرئيسي مباشرة دون تفريع
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // جلب حساب المخزون الرئيسي الثابت من الشجرة باستخدام الثابت المعرف في الموديل
        $parentAccount = Account::where('code', Account::CODE_INVENTORY)->firstOrFail();

        // إنشاء سجل المخزن في جدول المخازن فقط وربطه بالحساب التجميعي الثابت
        $store = Store::create([
            'name'       => $validatedData['name'],
            'location'   => $validatedData['location'] ?? null,
            'account_id' => $parentAccount->id, // يصب في حساب المخزون الرئيسي مباشرة
            'is_active'  => true,
        ]);

        // [تعديل]: جلب علاقة الحساب فوراً بعد الحفظ لتضمينها في الـ Resource الراجع للاستجابة الفورية في الواجهة
        $store->load('account');

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المخزن بنجاح وربطه بالحساب التجميعي الرئيسي.',
            'data'    => new StoreResource($store)
        ], 201);
    }

    /**
     * عرض تفاصيل مخزن محدد
     */
    public function show(Store $store): JsonResponse
    {
        // [تعديل]: تم إضافة load('account') لضمان عدم فقدان بيانات الحساب المالي عند عرض بيانات مخزن واحد
        $store->load('account')
              ->loadSum('journalLines', 'debit')
              ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'data'    => new StoreResource($store)
        ]);
    }

    /**
     * تحديث بيانات المخزن فقط
     */
    public function update(UpdateWarehouseRequest $request, Store $store): JsonResponse
    {
        $store->update($request->validated());

        // [تعديل]: تم إضافة load('account') لضمان رجوع كائن الحساب في الـ Resource وتفادي ظهور القيمة فارغة بعد التحديث في الواجهة
        $store->load('account')
              ->loadSum('journalLines', 'debit')
              ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المخزن بنجاح.',
            'data'    => new StoreResource($store)
        ]);
    }

    /**
     * حذف سجل المخزن فقط (الحساب التجميعي ثابت في الشجرة لا يحذف)
     */
    public function destroy(Store $store): JsonResponse
    {
        // حذف سجل المخزن (حذف ناعم)
        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المخزن بنجاح.'
        ]);
    }
}
