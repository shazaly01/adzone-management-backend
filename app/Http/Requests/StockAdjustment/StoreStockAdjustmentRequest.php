<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    /**
     * تفويض الصلاحية بناءً على الـ Policy الحارس للموديل
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\StockAdjustment::class);
    }

    /**
     * قواعد التحقق الصارمة لبيانات التسوية والكميات بناءً على معمارية المصفوفة السعرية
     */
    public function rules(): array
    {
        return [
            'store_id'                 => ['required', 'exists:stores,id'],
            'adjustment_date'          => ['required', 'date'],
            'notes'                    => ['nullable', 'string', 'max:1000'],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.item_id'          => ['required', 'exists:items,id'],

            // التعديل المعماري: فحص معرف سطر الوحدة من مصفوفة الصنف مباشرة ومنع تكرار نفس الوحدة
            'items.*.item_unit_id'     => ['required', 'exists:item_units,id', 'distinct'],

            'items.*.book_quantity'    => ['required', 'numeric'], // الكمية الدفترية بالنظام
            'items.*.physical_quantity'=> ['required', 'numeric', 'min:0'], // الكمية الفردية الفعلية لا يمكن أن تكون سالبة
            'items.*.unit_cost'        => ['required', 'numeric', 'min:0'], // تكلفة الصنف الحالية في المصفوفة
        ];
    }

    /**
     * تخصيص أسماء الحقول للغة العربية لتظهر رسائل الفحص بشكل محاسبي دقيق
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
