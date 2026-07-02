<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // التقاط معرف المخزن الحالي المرسل من فلاتر الواجهة الأمامية
        $currentStoreId = $request->input('store_id');

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'item_type'      => $this->item_type,
            'profit_margin'  => (float) $this->profit_margin,
            'category_id'    => $this->category_id,
            'category_path'  => $this->category_path,
            'category_name'  => $this->category?->name,
            'base_unit_id'   => $this->base_unit_id,
            'base_unit_name' => $this->baseUnit?->name,
            'is_active'      => (bool) $this->is_active,
            'is_dimensional' => (bool) $this->is_dimensional,
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),

            // التعديل المعماري: حقن المخزون اللحظي الفعلي بناءً على المخزن المحدد في طلب الفلترة الحالي
            'current_stock'  => (float) ($this->stocks->where('store_id', $currentStoreId)->first()?->current_quantity ?? 0),

            // [إضافة]: حقن حد الطلب الفعلي المخصص لهذا المخزن بالتحديد لإطلاقه في شاشات التنبيه
            'reorder_level'   => (float) ($this->stocks->where('store_id', $currentStoreId)->first()?->reorder_level ?? 0),

            // تجميع الهيكل الشجري اللانهائي والمصفوفة السعرية بالكامل للفرونت إند
            'units' => $this->units->map(function ($itemUnit) {
                return [
                    'id'                => $itemUnit->id,
                    'unit_id'           => $itemUnit->unit_id,
                    'unit_name'         => $itemUnit->unit?->name,
                    'conversion_factor' => (float) $itemUnit->conversion_factor,
                    'cost'              => (float) $itemUnit->cost,
                    'price'             => (float) $itemUnit->price,

                    // الباركودات اللانهائية التابعة لهذه الوحدة بالتحديد
                    'barcodes' => $itemUnit->barcodes->pluck('barcode'),

                    // مصفوفة الأسعار والخصومات التابعة لهذه الوحدة (Pricing Matrix)
                    'prices' => $itemUnit->prices->map(function ($unitPrice) {
                        return [
                            'id'                  => $unitPrice->id,
                            'price_list_id'       => $unitPrice->price_list_id,
                            'price_list_name'     => $unitPrice->priceList?->name,
                            'discount_percentage' => (float) $unitPrice->discount_percentage,
                            'price'               => (float) $unitPrice->price,
                        ];
                    }),
                ];
            }),
        ];
    }
}
