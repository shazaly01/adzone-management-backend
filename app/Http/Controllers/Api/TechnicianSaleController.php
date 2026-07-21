<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use App\Http\Requests\Sale\SwapRawMaterialRequest;
use App\Http\Resources\Api\SaleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Exception;

class TechnicianSaleController extends Controller
{
    protected SaleService $saleService;

    /**
     * حقن محرك الخدمة اللوجستية للمبيعات لحفظ النمط النحيف للمتحكم
     */
    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * استعراض قائمة الفواتير النهائية التي تحتوي على مواد خام وجاهزة لتنفيذ الفني مع تطبيق الفلاتر كاملة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // التحقق الآمن من الصلاحية عبر حزمة Spatie
        if (!$request->user()->hasPermissionTo('sale.swap_raw_materials')) {
            abort(403, 'عذراً، أنت لا تملك الصلاحية الكافية لاستعراض شاشة الفني.');
        }

        // جلب الفواتير النهائية مع تطابق فلاتر المبيعات بالكامل داخل الـ Controller مباشرة
        $sales = Sale::with(['store', 'customer', 'user', 'items.item', 'items.itemUnit.unit'])
            ->whereNotNull('journal_entry_id')
            ->where('invoice_type', 'sale')
            ->whereHas('items.item', function ($query) {
                $query->where('is_dimensional', true); // الفرز بناءً على طبيعة الصنف المترية فقط
            })
            // 1. فلتر البحث السريع (رقم الفاتورة، المعرف الرقمي، أو الملاحظات)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            // 2. فلتر مخزن الصرف
            ->when($request->filled('store_id'), function ($query) use ($request) {
                $query->where('store_id', $request->store_id);
            })
            // 3. فلتر حساب العميل
            ->when($request->filled('customer_id'), function ($query) use ($request) {
                $query->where('customer_id', $request->customer_id);
            })
            // 4. فلتر تاريخ البداية (من تاريخ)
            ->when($request->filled('from_date'), function ($query) use ($request) {
                $query->whereDate('invoice_date', '>=', $request->from_date);
            })
            // 5. فلتر تاريخ النهاية (إلى تاريخ)
            ->when($request->filled('to_date'), function ($query) use ($request) {
                $query->whereDate('invoice_date', '<=', $request->to_date);
            })
            ->latest('invoice_date')
            ->paginate(15);

        return SaleResource::collection($sales);
    }

    /**
     * عرض تفاصيل فاتورة معينة للفني لمراجعة ومقارنة الخامات وأبعادها المتيرية
     */
    public function show(Sale $sale): JsonResponse
    {
        // التحقق من صلاحية الوصول للفاتورة المحددة بناءً على الـ Policy
        $this->authorize('swapRawMaterials', $sale);

        $sale->load(['items.item', 'items.itemUnit.unit', 'store', 'customer', 'journalEntry']);

        return response()->json([
            'success' => true,
            'data'    => new SaleResource($sale)
        ]);
    }

    /**
     * معالجة التبديل الذري للخامات من واجهة الفني مع تأمين ثبات القيم المالية للعميل وتحديث الحالة
     */
    public function swapRawMaterials(SwapRawMaterialRequest $request, Sale $sale): JsonResponse
    {
        // 1. التحقق الصارم من الصلاحية البرمجية للمستند عبر الـ Policy
        $this->authorize('swapRawMaterials', $sale);

        try {
            // 2. تمرير المعاملات الثلاثة كاملة (الفاتورة، المصفوفة، والحالة التشغيلية الجديدة المعتمدة)
            $updatedSale = $this->saleService->swapRawMaterials(
                $sale,
                $request->validated()['items'],
                $request->validated()['production_status']
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تبديل خامات الفاتورة بنجاح، وتحديث الحركات المخزنية والتكلفة الفعلية دون المساس بحساب العميل.',
                'data'    => new SaleResource($updatedSale->load(['items.item', 'items.itemUnit.unit', 'store', 'customer']))
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية تبديل خامات الفني: ' . $e->getMessage()
            ], 500);
        }
    }
}
