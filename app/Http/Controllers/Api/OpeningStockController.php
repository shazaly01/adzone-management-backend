<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpeningStock;
use App\Http\Requests\OpeningStock\OpeningStockRequest;
use App\Http\Resources\Api\OpeningStockResource;
use App\Services\OpeningStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth; // استيراد حارس الحماية الصريح

class OpeningStockController extends Controller
{
    use AuthorizesRequests;

    protected OpeningStockService $openingStockService;

    public function __construct(OpeningStockService $openingStockService)
    {
        $this->openingStockService = $openingStockService;
    }

    /**
     * استعراض قائمة مستندات بضاعة أول المدة مع الـ Pagination والتحميل المسبق المطور للأداء
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OpeningStock::class);

        // شحن الأصناف ومصفوفة وحداتها الجديدة مسبقاً لحماية الخادم من استعلامات N+1
        $openingStocks = OpeningStock::with(['store', 'user', 'journalEntry', 'items.item', 'items.itemUnit.unit'])
            ->latest('id')
            ->paginate($request->get('per_page', 15));

        return OpeningStockResource::collection($openingStocks)->response();
    }

    /**
     * حفظ ومأسسة مستند بضاعة أول مدة جديد وتوليد قيده المالي فوراً عبر الخدمة
     */
    public function store(OpeningStockRequest $request): JsonResponse
    {
        $this->authorize('create', OpeningStock::class);

        // التعديل الهيكلي: استبدال auth()->id() بـ Auth::id() لحل مشكلة التعريف الاستاتيكي تماماً
        $openingStock = $this->openingStockService->createOpeningStock(
            $request->validated(),
            Auth::id()
        );

        return (new OpeningStockResource($openingStock))
            ->additional([
                'success' => true,
                'message' => 'تم حفظ مستند بضاعة أول المدة، وحقن الكميات بالمخازن وتوليد القيد التأسيسي بنجاح.'
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * عرض تفاصيل مستند بضاعة أول مدة مفرد مع سطور الأصناف وعلاقة العبور الجديدة
     */
    public function show(OpeningStock $openingStock): JsonResponse
    {
        $this->authorize('view', $openingStock);

        // استبدال تحميل الـ items.unit القديمة بعلاقة العبور الآمنة للمصفوفة items.itemUnit.unit
        $openingStock->load(['items.item', 'items.itemUnit.unit', 'store', 'user', 'journalEntry']);

        return (new OpeningStockResource($openingStock))
            ->additional(['success' => true])
            ->response();
    }

    /**
     * تعديل مستند قائمة وعكس الحركات المخزنية والمالية السابقة بأمان لقفل الفترة
     */
    public function update(OpeningStockRequest $request, OpeningStock $openingStock): JsonResponse
    {
        $this->authorize('update', $openingStock);

        // التعديل الهيكلي: استبدال auth()->id() بـ Auth::id() لحل مشكلة التعريف الاستاتيكي تماماً
        $updatedStock = $this->openingStockService->updateOpeningStock(
            $openingStock,
            $request->validated(),
            Auth::id()
        );

        return (new OpeningStockResource($updatedStock))
            ->additional([
                'success' => true,
                'message' => 'تم تحديث مستند بضاعة أول المدة وإعادة احتساب الأثر المخزني والمالي بنجاح.'
            ])
            ->response();
    }

    /**
     * حذف مستند تسوية أول المدة ناعماً وإزاحة أثره بالكامل ميكانيكياً عبر الخدمة المختصة
     */
    public function destroy(OpeningStock $openingStock): JsonResponse
    {
        $this->authorize('delete', $openingStock);

        $this->openingStockService->deleteOpeningStock($openingStock);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف مستند بضاعة أول المدة ناعماً وعكس كامل أثره المالي واللوجستي من الدفاتر والمخازن.'
        ]);
    }
}
