<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\VoucherService;
use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Http\Requests\Voucher\UpdateVoucherRequest;
use App\Http\Resources\Api\VoucherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Exception;

class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
        $this->authorizeResource(Voucher::class, 'voucher');
    }

    /**
     * استعراض قائمة السندات المالية الموحدة مع تفعيل التصفية والفلترة الذكية الممررة من الواجهة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // استقبال كائن الطلب وتفويض الفلترة والبحث لطبقة الـ Service
        $vouchers = $this->voucherService->getPaginatedVouchers($request->all(), 15);

        return VoucherResource::collection($vouchers);
    }

    /**
     * تنظيم وحفظ سند مالي جديد وتوليد قيوده وحركاتها فورا
     */
    public function store(StoreVoucherRequest $request): JsonResponse
    {
        try {
            $voucher = $this->voucherService->createVoucher(
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ السند المالي وتوليد القيود الحسابية المرتبطة به بنجاح.',
                'data'    => new VoucherResource($voucher)
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حفظ السند المالي: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل سند مالي مفرد مع روابط حساباته العامة والمساعدة
     */
    public function show(Voucher $voucher): JsonResponse
    {
        // ترطيب السند المفرد بالعلاقات التحليلية المساعدة للنقدية فوراً
        $voucher->load(['account', 'fundAccount', 'treasury', 'bank', 'user', 'journalEntry']);

        return response()->json([
            'success' => true,
            'data'    => new VoucherResource($voucher)
        ]);
    }

    /**
     * تعديل السند المالي القائم وإعادة هيكلة وموازنة حركاته الحسابية
     */
    public function update(UpdateVoucherRequest $request, Voucher $voucher): JsonResponse
    {
        try {
            $updatedVoucher = $this->voucherService->updateVoucher($voucher, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث السند المالي وإعادة موازنة وترحيل القيود المالية بنجاح.',
                'data'    => new VoucherResource($updatedVoucher)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية تحديث السند المالي: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف المستند وعكس الأثر المالي من دفاتر اليومية فوراً
     */
    public function destroy(Voucher $voucher): JsonResponse
    {
        try {
            $this->voucherService->deleteVoucher($voucher);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف السند المالي الموحد وعكس أثره المحاسبي من النظام بالكامل.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت عملية حذف السند: ' . $e->getMessage()
            ], 500);
        }
    }
}
