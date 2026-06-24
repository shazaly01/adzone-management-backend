<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Resources\Api\UnitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    /**
     * تفعيل الحماية البرمجية للمتحكم بناءً على الـ Policy المعتمدة لجدول الوحدات
     */
    public function __construct()
    {
        $this->authorizeResource(Unit::class, 'unit');
    }

    /**
     * استعراض قائمة دليل الوحدات القياسية بالكامل
     */
    public function index(): AnonymousResourceCollection
    {
        $units = Unit::latest()->get();

        return UnitResource::collection($units);
    }

    /**
     * حفظ وحدة قياسية جديدة في الدليل
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الوحدة القياسية بنجاح.',
            'data'    => new UnitResource($unit)
        ], 201);
    }

    /**
     * عرض تفاصيل وحدة معينة
     */
    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new UnitResource($unit)
        ]);
    }

    /**
     * تعديل بيانات وحدة قائمة في الدليل
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الوحدة بنجاح.',
            'data'    => new UnitResource($unit)
        ]);
    }

    /**
     * حذف وحدة من الدليل (Soft Delete) مع تأمين الرقابة اللوجستية الصارمة
     */
    public function destroy(Unit $unit): JsonResponse
    {
        // حماية هندسية: يمنع حذف الوحدة إذا كانت مستخدمة كـ (وحدة أولى) في أي صنف مخزني
        if ($unit->itemsAsUnit1()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذه الوحدة لأنها مستخدمة كوحدة أساسية لبعض الأصناف في الدليل.'
            ], 422);
        }

        // حماية هندسية: يمنع حذف الوحدة إذا كانت مستخدمة كـ (وحدة ثانية) في أي صنف مخزني
        if ($unit->itemsAsUnit2()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذه الوحدة لأنها مستخدمة كوحدة فرعية ثانية لبعض الأصناف في الدليل.'
            ], 422);
        }

        // حماية هندسية: يمنع حذف الوحدة إذا كانت مستخدمة كـ (وحدة ثالثة) في أي صنف مخزني
        if ($unit->itemsAsUnit3()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذه الوحدة لأنها مستخدمة كوحدة فرعية ثالثة لبعض الأصناف في الدليل.'
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الوحدة من الدليل بنجاح.'
        ]);
    }
}
