<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    /**
     * تحويل مصفوفة سطور المبيعات إلى تنسيق JSON متناسق لبناء جدول الأصناف في الفاتورة المطبوعة والشاشات
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'sale_id'         => $this->sale_id,
            'item_id'         => $this->item_id,
            'item_name'       => $this->item->name ?? null,
            'item_code'       => $this->item->code ?? null,
            'item_type'       => $this->item->item_type ?? null,

            'is_dimensional'  => (bool) ($this->item->is_dimensional ?? false),
            'item_unit_id'    => $this->item_unit_id,
            'unit_name'       => $this->itemUnit?->unit?->name ?? null,

            // تمرير حقول الأبعاد المحدثة لدعم طباعة وعرض مقاسات الأصناف المترية
            'length'          => $this->length !== null ? (float) $this->length : null,
            'width'           => $this->width !== null ? (float) $this->width : null,

            // حالة خضوع السطر الحالي لعمولة المصمم
            'is_designed'     => (bool) $this->is_designed,

            'quantity'        => (float) $this->quantity,
            'unit_price'      => (float) $this->unit_price,
            'subtotal'        => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'grand_total'     => (float) $this->grand_total,

            // الأرصدة والوحدات المتاحة ديناميكياً لتحديث واجهة الكاشير التفاعلية
            'current_stock'   => (float) ($this->item->stocks->where('store_id', $this->sale->store_id)->first()?->current_quantity ?? 0),
            'available_units' => $this->item->units->map(function ($itemUnit) {
                return [
                    'id'                => $itemUnit->id,
                    'unit_id'           => $itemUnit->unit_id,
                    'unit_name'         => $itemUnit->unit?->name,
                    'conversion_factor' => (float) $itemUnit->conversion_factor,
                    'price'             => (float) $itemUnit->price,
                ];
            }),
        ];
    }
}
