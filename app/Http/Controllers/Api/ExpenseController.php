<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\Api\ExpenseResource;
use App\Models\Expense;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function __construct()
    {
        // تطبيق حماية الصلاحيات التلقائية عبر الـ Policy المرتبط بالموديل
        $this->authorizeResource(Expense::class, 'expense');
    }

    /**
     * عرض قائمة بنود المصروفات مع حساب الأرصدة الفعلية برمجياً
     */
    public function index(): JsonResponse
    {
        // [تعديل]: تم إضافة with('account') لجلب بيانات حساب المصروفات التشغيلية الإجمالي (4101) في جدول العرض
        $expenses = Expense::with('account')
                           ->withSum('journalLines', 'debit')
                           ->withSum('journalLines', 'credit')
                           ->latest()
                           ->get();

        return response()->json([
            'success' => true,
            'data'    => ExpenseResource::collection($expenses)
        ]);
    }

    /**
     * إنشاء بند مصروف جديد وربطه بحساب المصروفات التشغيلية الإجمالي مباشرة
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // جلب حساب المصروفات الإجمالي الثابت باستخدام الثابت المعرف في الموديل
        $parentAccount = Account::where('code', Account::CODE_EXPENSES)->firstOrFail();

        // إنشاء سجل المصروف في جدوله فقط وربطه بالحساب التجميعي الثابت
        $expense = Expense::create([
            'name'       => $validatedData['name'],
            'account_id' => $parentAccount->id, // يصب في حساب المصروفات الإجمالي الرئيسي
            'is_active'  => true,
        ]);

        // [تعديل]: جلب علاقة الحساب فوراً بعد الحفظ لتضمينها في الـ Resource الراجع للاستجابة الفورية في الواجهة
        $expense->load('account');

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء بند المصروف بنجاح وربطه بالحساب التجميعي الرئيسي.',
            'data'    => new ExpenseResource($expense)
        ], 201);
    }

    /**
     * عرض تفاصيل بند مصروف محدد
     */
    public function show(Expense $expense): JsonResponse
    {
        // [تعديل]: تم إضافة load('account') لضمان عدم فقدان بيانات الحساب المالي عند عرض تفاصيل بند مصروف واحد
        $expense->load('account')
                ->loadSum('journalLines', 'debit')
                ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'data'    => new ExpenseResource($expense)
        ]);
    }

    /**
     * تحديث بيانات بند المصروف فقط
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->update($request->validated());

        // [تعديل]: تم إضافة load('account') لضمان رجوع كائن الحساب في الـ Resource وتفادي ظهور القيمة فارغة بعد التحديث في الواجهة
        $expense->load('account')
                ->loadSum('journalLines', 'debit')
                ->loadSum('journalLines', 'credit');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بند المصروف بنجاح.',
            'data'    => new ExpenseResource($expense)
        ]);
    }

    /**
     * حذف سجل بند المصروف فقط (الحساب التجميعي ثابت في الشجرة لا يحذف)
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف بند المصروف بنجاح.'
        ]);
    }
}
