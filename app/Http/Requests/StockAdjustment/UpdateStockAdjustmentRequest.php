<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockAdjustmentRequest extends FormRequest
{
    /**
     * تفويض الصلاحية بناءً على الـ Policy الحارس للموديل عند التعديل
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('stock_adjustment'));
    }

    /**
     * قواعد التحقق الصارمة لبيانات التسوية والكميات عند التعديل بناءً على معمارية المصفوفة
     */
    public function rules(): array
    {
        return [
            'store_id'                 => ['required', 'exists:stores,id'],
            'adjustment_date'          => ['required', 'date'],
            'notes'                    => ['nullable', 'string', 'max:1000'],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.item_id'          => ['required', 'exists:items,id'],

            // التعديل المعماري: فحص معرف سطر الوحدة من مصفوفة الصنف مباشرة بدلاً من الوحدة المجردة ومنع التكرار
            'items.*.item_unit_id'     => ['required', 'exists:item_units,id', 'distinct'],

            'items.*.book_quantity'    => ['required', 'numeric'],
            'items.*.physical_quantity'=> ['required', 'numeric', 'min:0'],
            'items.*.unit_cost'        => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * تخصيص أسماء الحقول لرسائل الخطأ العربية لتظهر بشكل محاسبي منظم
     */
    public function attributes(): array
    {
        return [
            'store_id'                  => 'المستودع الجاري جرده',
            'adjustment_date'           => 'تاريخ مستند التسوية',
            'notes'                     => 'أسباب التسوية الجردية',
            'items'                     => 'عناصر وأصناف الجرد',
            'items.*.item_id'           => 'الصنف المخزني',
            'items.*.item_unit_id'      => 'وحدة الصنف',
            'items.*.book_quantity'     => 'الكمية الدفترية',
            'items.*.physical_quantity' => 'الكمية الفردية الفعلية',
            'items.*.unit_cost'         => 'تكلفة الوحدة',
        ];
    }
}
