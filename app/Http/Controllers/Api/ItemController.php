<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest; // [تعديل التوافق]: استيراد ريكويست التحديث المطور
use App\Http\Resources\Api\ItemResource;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Requests\Item\RefreshStockRequest;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    protected ItemService $itemService;

    /**
     * حقن طبقة الخدمة الحركية داخل الـ Controller
     */
    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * جلب قائمة الأصناف بناءً على محركات الفلترة والبحث المتقدم بالباركود
     */
   /**
     * جلب قائمة الأصناف بناءً على محركات الفلترة والبحث المتقدم مدمجاً بها المخزون اللحظي
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Item::class);

        // التحقق من وجود المخزن في شاشات العمليات (مبيعات/مشتريات) لضمان دقة القراءة
        $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id']
        ], [
            'store_id.required' => 'عفواً، يجب تحديد المخزن أولاً قبل البدء بالبحث عن الأصناف.'
        ]);

        $filters = $request->only(['search', 'item_type', 'category_id', 'is_active']);

        // استدعاء محرك البحث المطور من طبقة الخدمة وحقن المخزون اللحظي مباشرة
        $items = $this->itemService->searchWithStock($filters, (int) $request->store_id);

        return ItemResource::collection($items);
    }

    /**
     * تسجيل صنف جديد في النظام وتفويض العملية بالكامل للـ Service
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $this->authorize('create', Item::class);

        $item = $this->itemService->create($request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل الصنف الجديد ومصفوفة أسعاره بنجاح في النظام.',
            'data'    => new ItemResource($item)
        ], 201);
    }

    /**
     * عرض تفاصيل الصنف بالكامل شجرياً مع مصفوفات الوحدات والباركودات
     */
    public function show(Item $item): ItemResource
    {
        $this->authorize('view', $item);

        return new ItemResource($item->load(['units.unit', 'units.barcodes', 'units.prices.priceList', 'baseUnit', 'category']));
    }

    /**
     * تحديث ومزامنة بيانات الصنف ومصفوفاته اللانهائية
     */
    public function update(UpdateItemRequest $request, string $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $this->authorize('update', $item);

        // [تعديل التوافق]: تمرير ريكويست التحديث المعتمد لضمان تخطي فحص باركودات الصنف نفسه
        $updatedItem = $this->itemService->update((int)$id, $request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث بيانات الصنف ومزامنة مصفوفة أسعاره بنجاح.',
            'data'    => new ItemResource($updatedItem)
        ], 200);
    }

    /**
     * حذف الصنف وحذف مصفوفاته بشكل أرشيفي ناعم (Soft Delete)
     */
    public function destroy(string $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $this->authorize('delete', $item);

        $this->itemService->delete((int)$id);

        return response()->json([
            'status'  => true,
            'message' => 'تم استبعاد وحذف الصنف بنجاح من قاعدة البيانات النشطة.'
        ], 200);
    }



    /**
     * تحديث فوري عالي الأداء لكميات الأصناف المفتوحة بالشبكة عند تغيير المخزن
     */
    public function refreshStock(RefreshStockRequest $request): AnonymousResourceCollection
    {
        // التحقق من صلاحية العرض العام للأصناف مركزياً
        $this->authorize('viewAny', Item::class);

        $updatedStocks = $this->itemService->refreshStockLevels(
            (int) $request->validated()['store_id'],
            $request->validated()['item_ids']
        );

        // إعادة مصفوفة خفيفة وموحدة الهيكلية عبر نفس الـ Resource
        return ItemResource::collection($updatedStocks);
    }
}
