<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Http\Resources\Api\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Exception;

class StockAdjustmentController extends Controller
{
    protected StockAdjustmentService $adjustmentService;

    public function __construct(StockAdjustmentService $adjustmentService)
    {
        $this->adjustmentService = $adjustmentService;

        // تطبيق حماية الصلاحيات المعتمدة على Spatie عبر الـ Policy تلقائياً على مستوى المصادر
        $this->authorizeResource(StockAdjustment::class, 'stock_adjustment');
    }

    /**
     * عرض قائمة مستندات التسوية الجردية المسجلة بالمنظومة مع التصفح الموحد
     */
    public function index(): AnonymousResourceCollection
    {
        // شحن الأصناف ومصفوفة وحداتها الجديدة مسبقاً لحماية الخادم من استعلامات N+1 داخل الـ Resource
        $adjustments = StockAdjustment::with(['store', 'user', 'journalEntry', 'items.item', 'items.itemUnit.unit'])
            ->latest('adjustment_date')
            ->paginate(15);

        return StockAdjustmentResource::collection($adjustments);
    }

    /**
     * تنظيم وحفظ مستند تسوية جردية جديد وتوليد حركاته الكمية وقيوده المالية فوراً
     */
    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        try {
            $adjustment = $this->adjustmentService->createAdjustment(
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل مستند التسوية الجردية وتحديث الكميات اللحظية والقيود بنجاح.',
                'data'    => new StockAdjustmentResource($adjustment)
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حفظ التسوية الجردية: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل مستند تسوية جردية محدد مع أسطر الأصناف والفروقات وعلاقة العبور الجديدة
     */
    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        // استبدال العلاقة الخطية القديمة items.unit بعلاقة العبور الآمنة للمصفوفة items.itemUnit.unit
        $stockAdjustment->load(['items.item', 'items.itemUnit.unit', 'store', 'user', 'journalEntry']);

        return response()->json([
            'success' => true,
            'data'    => new StockAdjustmentResource($stockAdjustment)
        ]);
    }

    /**
     * تعديل مستند تسوية جردية قائم (متاح في نفس اليوم فقط ويتم حظره تاريخياً عبر الـ Service)
     */
    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        try {
            $updatedAdjustment = $this->adjustmentService->updateAdjustment(
                $stockAdjustment,
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث مستند التسوية الجردية وإعادة احتساب الأرصدة المخزنية والقيود المحاسبية بنجاح.',
                'data'    => new StockAdjustmentResource($updatedAdjustment)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422); // كود 422 يعبر عن رفض المعاملة بناءً على منطق الأعمال المحاسبي الصارم
        }
    }

    /**
     * حذف مستند التسوية الجردية ناعماً وعكس كامل حركاته وأثره المالي من شجرة الحسابات فوراً
     */
    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        try {
            // تصحيح الخطأ المطبعي هنا واستدعاء الخاصية بالشكل الصحيح لعمل الحذف الآمن
            $this->adjustmentService->deleteAdjustment($stockAdjustment);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف مستند التسوية الجردية وعكس أثره المالي والمخزني من المنظومة بنجاح.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حذف مستند التسوية: ' . $e->getMessage()
            ], 500);
        }
    }
}
