<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Http\Resources\Api\PurchaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Exception;

class PurchaseController extends Controller
{
    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
        $this->authorizeResource(Purchase::class, 'purchase');
    }

    /**
     * استعراض قائمة فواتير المشتريات والمرتجع مع التحميل المسبق للعلاقات لتوحيد هيكل الرد
     */
    public function index(): AnonymousResourceCollection
    {
        // التعديل المعماري: شحن الفاتورة عبر علاقة العبور الآمن المحدثة items.itemUnit.unit حماية من الـ N+1
        $purchases = Purchase::with(['store', 'supplier', 'user', 'items.item', 'items.itemUnit.unit'])
            ->latest('invoice_date')
            ->paginate(15);

        return PurchaseResource::collection($purchases);
    }

    /**
     * حفظ مستند مشتريات أو مرتجع جديد وتحديث المخازن والقيود المالية فوراً
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->purchaseService->createPurchase(
                $request->validated(),
                $request->user()->id
            );

            // [تعديل تكميلي]: شحن العلاقات السيادية للفاتورة لخدمة الـ Resource ومنع الـ N+1
            $purchase->load(['store', 'supplier', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ مستند المشتريات وتحديث المخازن والقيود بنجاح.',
                'data'    => new PurchaseResource($purchase)
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حفظ الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل فاتورة مشتريات معينة مع كافة سطورها وعلاقتها الشجرية والمالية
     */
    public function show(Purchase $purchase): JsonResponse
    {
        // التعديل المعماري البنيوي: شحن علاقة الوحدات الكاملة للصنف لمنع ثغرة اختفاء الأصناف في الفرونت إيند
        $purchase->load([
            'items.purchase',          // تأمين مرجع الفاتورة الأم لكل سطر لحماية store_id
            'items.item.stocks',       // شحن الأرصدة اللحظية للمخازن فوراً
            'items.item.units.unit',
            'items.itemUnit.unit',
            'store',
            'supplier',
            'parentInvoice',
            'journalEntry',
            'user'
        ]);

        return response()->json([
            'success' => true,
            'data'    => new PurchaseResource($purchase)
        ]);
    }

    /**
     * تعديل وتحديث فاتورة المشتريات القائمة وعكس الأثر المخزني والمالي تلقائياً
     */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        try {
            $updatedPurchase = $this->purchaseService->updatePurchase($purchase, $request->validated());

            // [تعديل تكميلي]: إعادة ترطيب كائن الفاتورة بالعلاقات التحليلية بعد التحديث لسلامة الـ Resource
            $updatedPurchase->load(['store', 'supplier', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الفاتورة وإعادة موازنة المخازن والقيود بنجاح.',
                'data'    => new PurchaseResource($updatedPurchase)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية التحديث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحذف الأرشيفي للفاتورة وعكس كافة حركاتها المخزنية والمالية من دفاتر اليومية
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            $this->purchaseService->deletePurchase($purchase);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف مستند المشتريات وعكس كافة حركاته المخزنية والمالية بنجاح.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية الحذف: ' . $e->getMessage()
            ], 500);
        }
    }
}
