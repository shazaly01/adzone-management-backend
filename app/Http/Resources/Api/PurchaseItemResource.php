<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    /**
     * تحويل مصفوفة السطور إلى تنسيق JSON متناسق للواجهة الأمامية مع حقن الوحدات والمخزون اللحظي الفعلي
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'purchase_id'     => $this->purchase_id,
            'item_id'         => $this->item_id,
            'item_name'       => $this->item->name ?? null, // جلب اسم الصنف مباشرة لتسهيل العرض في الجداول
            'item_code'       => $this->item->code ?? null,
            'item_type'       => $this->item->item_type ?? null,

            // التعديل المعماري: تعويض معرف الوحدة العام بمعرف سطر مصفوفة الوحدات المطور
            'item_unit_id'    => $this->item_unit_id,
            'unit_name'       => $this->itemUnit?->unit?->name ?? null, // جلب اسم الوحدة بالعبور الآمن (كرتون، حبة...)

            'quantity'        => (float) $this->quantity,
            'unit_cost'       => (float) $this->unit_cost,
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'grand_total'     => (float) $this->grand_total,

            // حقن المخزون اللحظي الفعلي للصنف الآن بناءً على مستودع حركية المشتريات الحالية
            'current_stock'   => (float) ($this->item->stocks->where('store_id', $this->purchase->store_id)->first()?->current_quantity ?? 0),

            // التعديل المعماري الجذري: حقن الوحدات البديلة الكاملة مضافاً إليها معاملات التحويل لسلامة الحسابات بالفرونت إيند
            'available_units' => $this->item->units->map(function ($itemUnit) {
                return [
                    'id'                => $itemUnit->id,
                    'unit_id'           => $itemUnit->unit_id,
                    'unit_name'         => $itemUnit->unit?->name ?? null,
                    'conversion_factor' => (float) $itemUnit->conversion_factor, // حرج جداً لحسابات الأرصدة المتاحة للوحدات المتباينة
                    'cost'              => (float) $itemUnit->cost,
                    'price'             => (float) $itemUnit->price,
                ];
            }),
        ];
    }
}
