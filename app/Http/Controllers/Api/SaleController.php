<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Http\Requests\Sale\SwapRawMaterialRequest;
use App\Http\Resources\Api\SaleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Exception;

class SaleController extends Controller
{
    protected $saleService;

    /**
     * تفعيل الحماية البرمجية بناءً على الـ Policy وحقن محرك الخدمة اللوجستية للمبيعات
     */
    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
        $this->authorizeResource(Sale::class, 'sale');
    }

    /**
     * استعراض قائمة فواتير المبيعات ومرتجع الكاشير مع التحميل المسبق للعلاقات لتوحيد هيكل الرد
     */
    public function index(): AnonymousResourceCollection
    {
        // التعديل المعماري: شحن الفاتورة بكافة سطورها عبر علاقة العبور الآمن المحدثة items.itemUnit.unit لمنع الـ N+1 Query
        $sales = Sale::with(['store', 'customer', 'user', 'items.item', 'items.itemUnit.unit', 'treasury', 'bank'])
            ->latest('invoice_date')
            ->paginate(15);

        return SaleResource::collection($sales);
    }

    /**
     * حفظ فاتورة مبيعات كاشير أو مستند مرتجع جديد وتحديث المخازن والقيود فوراً
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->saleService->createSale(
                $request->validated(),
                $request->user()->id
            );

            // تأمين تحميل العلاقات الأساسية التي يتوقعها الـ Resource للعرض النظيف
            $sale->load(['store', 'customer', 'user', 'treasury', 'bank']);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ فاتورة المبيعات وتحديث حسابات العميل والصندوق والمخازن بنجاح.',
                'data'    => new SaleResource($sale)
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حفظ الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل فاتورة مبيعات معينة مع كافة سطورها وأصنافها المباعة وعلاقتها الشجرية مجهزة للطباعة
     */
    public function show(Sale $sale): JsonResponse
    {
        // التعديل المعماري: إضافة الـ treasury والـ bank لضمان اكتمال بيانات الدفع المالي عند الطباعة في الواجهة الأمامية
        $sale->load([
            'items.item',
            'items.itemUnit.unit',
            'store',
            'customer',
            'parentInvoice',
            'journalEntry',
            'user',
            'treasury',
            'bank'
        ]);

        return response()->json([
            'success' => true,
            'data'    => new SaleResource($sale)
        ]);
    }

    /**
     * التعديل والتحديث الفوري لفاتورة المبيعات وعكس الأثر المالي والمخزني تلقائياً
     */
    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        try {
            $updatedSale = $this->saleService->updateSale($sale, $request->validated());

            // إعادة ترطيب العلاقات الأساسية بعد التعديل لمنع الكراش في الـ Resource
            $updatedSale->load(['store', 'customer', 'user', 'treasury', 'bank']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث فاتورة المبيعات وإعادة موازنة المستودعات والقيود المالية بنجاح.',
                'data'    => new SaleResource($updatedSale)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية التحديث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحذف الأرشيفي (Soft Delete) وعكس كافة حركات المخازن والحسابات المرتبطة بالفاتورة
     */
    public function destroy(Sale $sale): JsonResponse
    {
        try {
            $this->saleService->deleteSale($sale);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف مستند المبيعات وعكس أثره المالي واللوجستي من النظام بالكامل.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية الحذف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تبديل خامات الفاتورة بواسطة الفني وإعادة توازن المستودعات والـ Resource الموحد
     */
    public function swapRawMaterials(
        SwapRawMaterialRequest $request,
        Sale $sale
    ): JsonResponse {
        // 1. التحقق من صلاحية الفني عبر الـ Policy
        $this->authorize('swapRawMaterials', $sale);

        // 2. تمرير البيانات المعتمدة للخدمة لتنفيذ التبديل الذري عبر المتغير المحقن في الكلاس
        $updatedSale = $this->saleService->swapRawMaterials($sale, $request->validated()['items']);

        // 3. شحن الفاتورة بالعلاقات الجردية والتحليلية المعتمدة قبل الترحيل للواجهة الأمامية
        $updatedSale->load(['store', 'customer', 'user', 'items.item', 'items.itemUnit.unit', 'treasury', 'bank']);

        // 4. إعادة الاستجابة الموحدة بنظام الحسابات المساعدة المتسق
        return response()->json([
            'success' => true,
            'message' => 'تم تبديل خامات الفاتورة وتحديث الأرصدة التكليفية والمخزنية بنجاح.',
            'data'    => new SaleResource($updatedSale)
        ]);
    }
}
