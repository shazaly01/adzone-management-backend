<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ItemStock;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * المحرك الرئيسي المحصن لجلب كافة مؤشرات وإحصائيات لوحة تحكم المدير اللوجستية
     */
    public function getManagerStats(array $filters): array
    {
        $fromDate = isset($filters['from_date'])
            ? Carbon::parse($filters['from_date'])->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $toDate = isset($filters['to_date'])
            ? Carbon::parse($filters['to_date'])->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        $financialAndMeterStats = $this->calculateFinancialAndMeterStats($fromDate, $toDate);
        $reorderItems = $this->getReorderLevelItems();
        $operationalStats = $this->getOperationalStats($fromDate, $toDate);

        return [
            'date_range' => [
                'from' => $fromDate->toDateString(),
                'to'   => $toDate->toDateString(),
            ],
            'net_square_meters'   => (float) ($financialAndMeterStats['net_square_meters'] ?? 0.00),
            'net_sales_amount'    => (float) ($financialAndMeterStats['net_sales_amount'] ?? 0.00),
            'top_items_by_meters' => $financialAndMeterStats['top_items'], // مصفوفة الخامات كاملة مرتبة بالأعلى استهلاكاً
            'reorder_level_items' => $reorderItems,
            'operational_volume'  => $operationalStats,
        ];
    }

 /**
     * حساب الإحصائيات المالية ومعدلات استهلاك الخامات بالأمتار المربعة لكل صنف دون حد أقصى (نسخة محسنة الأداء)
     */
    private function calculateFinancialAndMeterStats(Carbon $from, Carbon $to): array
    {
        // 1. جلب صافي المبيعات المالية في خطوة واحدة
        $salesData = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereBetween('invoice_date', [$from, $to])
            ->select([
                DB::raw("SUM(CASE WHEN invoice_type = 'sale' THEN grand_total ELSE -grand_total END) as net_sales"),
            ])
            ->first();

        // 2. جلب الحركات التفصيلية للأصناف مجمعة (استعلام واحد فقط يربط الجداول الثلاثة)
        $allConsumedItems = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->whereNull('sales.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.is_dimensional', true)
            ->whereBetween('sales.invoice_date', [$from, $to])
            ->select([
                'sale_items.item_id',
                'items.name as item_name',
                DB::raw("SUM(CASE WHEN sales.invoice_type = 'sale'
                    THEN (COALESCE(sale_items.length, 0) * COALESCE(sale_items.width, 0) * COALESCE(sale_items.quantity, 0))
                    ELSE -(COALESCE(sale_items.length, 0) * COALESCE(sale_items.width, 0) * COALESCE(sale_items.quantity, 0))
                    END) as consumed_meters")
            ])
            ->groupBy('sale_items.item_id', 'items.name')
            ->orderByDesc('consumed_meters')
            ->get();

        // 3. [تحسين حرج]: احتساب الإجمالي الكلي في الذاكرة عبر PHP لتوفير استعلام Join كامل على السيرفر
        $totalMeters = (float) $allConsumedItems->sum('consumed_meters');

        // 4. فلترة الأصناف ذات الاستهلاك الفعلي وتنسيق المصفوفة النهائية للواجهة
        $formattedItems = $allConsumedItems->filter(function ($item) {
            return $item->consumed_meters > 0.00;
        })->map(function ($item) {
            return [
                'item_id'         => $item->item_id,
                'item_name'       => $item->item_name,
                'consumed_meters' => (float) $item->consumed_meters
            ];
        })->values()->toArray();

        return [
            'net_sales_amount'  => $salesData->net_sales ?? 0.00,
            'net_square_meters' => $totalMeters, // النتيجة هنا مطابقة تماماً وبأداء أسرع
            'top_items'         => $formattedItems
        ];
    }

    /**
     * جلب الأصناف التي تجاوزت حد الطلب الفعلي المكون مسبقاً
     */
    private function getReorderLevelItems(): array
    {
        return ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->join('stores', 'item_stocks.store_id', '=', 'stores.id')
            ->whereNull('items.deleted_at')
            ->where('items.is_active', true)
            ->where('item_stocks.reorder_level', '>', 0.00)
            ->whereRaw('item_stocks.current_quantity <= item_stocks.reorder_level')
            ->orderBy('item_stocks.current_quantity')
            ->select([
                'items.id as item_id',
                'items.name as item_name',
                'stores.name as store_name',
                'item_stocks.current_quantity',
                'item_stocks.reorder_level'
            ])
            ->get()
            ->map(function ($stock) {
                return [
                    'item_id'          => $stock->item_id,
                    'item_name'        => $stock->item_name,
                    'store_name'       => $stock->store_name,
                    'current_quantity' => (float) $stock->current_quantity,
                    'reorder_level'    => (float) $stock->reorder_level,
                ];
            })
            ->toArray();
    }

    /**
     * جلب مؤشرات أحجام الفواتير وحالات الورشة الإنتاجية
     */
    private function getOperationalStats(Carbon $from, Carbon $to): array
    {
        $invoiceCounts = DB::table('sales')
            ->whereNull('deleted_at')
            ->whereBetween('invoice_date', [$from, $to])
            ->select([
                DB::raw("COUNT(CASE WHEN invoice_type = 'sale' THEN 1 END) as total_sales_invoices"),
                DB::raw("COUNT(CASE WHEN invoice_type = 'return' THEN 1 END) as total_return_invoices"),
                DB::raw("COUNT(CASE WHEN production_status = 'pending' THEN 1 END) as status_pending_count"),
                DB::raw("COUNT(CASE WHEN production_status = 'processing' THEN 1 END) as status_processing_count"),
                DB::raw("COUNT(CASE WHEN production_status = 'on_hold' THEN 1 END) as status_on_hold_count"),
                DB::raw("COUNT(CASE WHEN production_status = 'completed' THEN 1 END) as status_completed_count"),
            ])
            ->first();

        return [
            'total_sales_invoices'  => (int) ($invoiceCounts->total_sales_invoices ?? 0),
            'total_return_invoices' => (int) ($invoiceCounts->total_return_invoices ?? 0),
            'production_statuses'   => [
                'pending'    => (int) ($invoiceCounts->status_pending_count ?? 0),
                'processing' => (int) ($invoiceCounts->status_processing_count ?? 0),
                'on_hold'    => (int) ($invoiceCounts->status_on_hold_count ?? 0),
                'completed'  => (int) ($invoiceCounts->status_completed_count ?? 0),
            ]
        ];
    }
}
