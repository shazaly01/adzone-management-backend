<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\DashboardService;
use App\Services\FinancialDashboardService;
use App\Policies\DashboardPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;
    protected FinancialDashboardService $financialDashboardService;

    /**
     * حقن الخدمتين (اللوجستية والمالية) ذرياً لتطبيق الفصل النظيف للمسؤوليات
     */
    public function __construct(
        DashboardService $dashboardService,
        FinancialDashboardService $financialDashboardService
    ) {
        $this->dashboardService = $dashboardService;
        $this->financialDashboardService = $financialDashboardService;
    }

    /**
     * معالجة طلب جلب مؤشرات وإحصائيات الإدارة الشاملة (مالي ولوجستي ومخزني)
     */
    public function getStats(DashboardFilterRequest $request): JsonResponse
    {
        // 1. التحقق المعماري الصارم من الصلاحية الموحدة الجديدة لحماية لوحة التحكم للمدير
        Gate::authorize('manager', DashboardPolicy::class);

        $validatedData = $request->validated();

        // 2. جلب المؤشرات اللوجستية والمخزنية (أمتار، حد طلب، أحجام فواتير)
        $logisticStats = $this->dashboardService->getManagerStats($validatedData);

        // 3. جلب المؤشرات والتقارير المالية المخصصة (مصروفات، عملاء، مصممين)
        $financialStats = $this->financialDashboardService->getFinancialStats($validatedData);

        // 4. دمج المخرجات بسلاسة تامة لتوليد الاستجابة النهائية الموحدة للمدير
        $unifiedStats = array_merge($logisticStats, [
            'expenses_summary'  => $financialStats['expenses_data'],
            'customers_summary' => $financialStats['customers_data'],
            'designers_summary' => $financialStats['designers_data'],
        ]);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'data'    => $unifiedStats,
        ], 200);
    }
}
