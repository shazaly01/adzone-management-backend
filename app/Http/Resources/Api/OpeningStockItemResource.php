<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockItemResource extends JsonResource
{
    /**
     * تحويل تفاصيل سطر صنف بضاعة أول المدة إلى مصفوفة خفيفة وآمنة للواجهة
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'opening_stock_id' => $this->opening_stock_id,
            'item_id'          => $this->item_id,
            'item_name'        => $this->item->name ?? null,

            // التعديل المعماري: تمرير معرف سطر الوحدة في المصفوفة بدلاً من الوحدة المجردة
            'item_unit_id'     => $this->item_unit_id,

            // جلب اسم الوحدة بالعبور الآمن من خلال علاقة المصفوفة الوسيطة الجديدة
            'unit_name'        => $this->itemUnit?->unit?->name ?? null,

            // جلب معامل التحويل كمؤشر مساعد إضافي للفرونت إند عند الحاجة
            'conversion_factor'=> $this->itemUnit?->conversion_factor ? (float) $this->itemUnit->conversion_factor : 1.00,

            'quantity'         => (float) $this->quantity,
            'unit_cost'        => (float) $this->unit_cost,
            'subtotal'         => (float) $this->subtotal,
        ];
    }
}
