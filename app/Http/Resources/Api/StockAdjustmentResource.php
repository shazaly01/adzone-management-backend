<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    /**
     * تحويل مستند التسوية الجردية إلى بنية JSON المفرودة للواجهة الأمامية
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'adjustment_sequence' => $this->adjustment_sequence,
            'adjustment_number'   => $this->adjustment_number,
            'store_id'            => $this->store_id,
            'store_name'          => $this->store->name ?? null,
            'adjustment_date'     => $this->adjustment_date->format('Y-m-d H:i:s'),
            'notes'               => $this->notes,

            // بيانات الموظف المنشئ والروابط الحسابية والمالية
            'user_id'             => $this->user_id,
            'user_name'           => $this->user->full_name ?? $this->user->name ?? null,
            'journal_entry_id'    => $this->journal_entry_id,
            'journal_entry_no'    => $this->journalEntry->entry_number ?? null,

            // تفاصيل أسطر الأصناف المتأثرة بالجرد والفروقات الكمية والمالية
            'items'               => $this->items->map(function ($line) {
                return [
                    'id'                  => $line->id,
                    'item_id'             => $line->item_id,
                    'item_name'           => $line->item->name ?? null,
                    'item_code'           => $line->item->code ?? null,

                    // التعديل المعماري: تمرير معرف مصفوفة وحدات الصنف الجديد
                    'item_unit_id'        => $line->item_unit_id,
                    'unit_name'           => $line->itemUnit?->unit?->name ?? null, // جلب اسم وحدة الجرد بالعبور الآمن

                    // الكميات المحتسبة والكسور
                    'book_quantity'       => (float) $line->book_quantity,
                    'physical_quantity'   => (float) $line->physical_quantity,
                    'quantity_difference' => (float) $line->quantity_difference, // (فعلية - دفترية)
                    'direction'           => $line->quantity_difference >= 0 ? 'surplus' : 'shortage',
                    'direction_lbl'       => $line->quantity_difference >= 0 ? 'فائض جردي' : 'عجز جردي',

                    // التقييم المالي اللحظي للسطر
                    'unit_cost'           => (float) $line->unit_cost,
                    'total_line_cost'     => (float) abs($line->quantity_difference * $line->unit_cost),
                'current_stock'       => (float) ($line->item->stocks->where('store_id', $this->store_id)->first()?->current_quantity ?? 0),

                    // 🛠️ [الحقن المعماري المضاف]: تمرير مصفوفة الوحدات البديلة الكاملة ومعاملات التحويل لمنع تجمد الحسابات بالواجهة
                    'available_units'     => $line->item->units->map(function ($itemUnit) {
                        return [
                            'id'                => $itemUnit->id,
                            'unit_id'           => $itemUnit->unit_id,
                            'unit_name'         => $itemUnit->unit?->name ?? null,
                            'conversion_factor' => (float) $itemUnit->conversion_factor,
                            'cost'              => (float) $itemUnit->cost,
                            'price'             => (float) $itemUnit->price,
                        ];
                    }),
                    ];
            })->toArray(),
        ];
    }
}
