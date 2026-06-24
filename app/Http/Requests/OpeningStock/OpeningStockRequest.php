<?php

namespace App\Http\Requests\OpeningStock;

use Illuminate\Foundation\Http\FormRequest;

class OpeningStockRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإتمام هذا الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * القواعد الصارمة للتحقق من صحة مدخلات بضاعة أول المدة
     */
    public function rules(): array
    {
        return [
            'store_id'             => ['required', 'integer', 'exists:stores,id'],
            'opening_date'         => ['required', 'date', 'date_format:Y-m-d H:i:s'],
            'notes'                => ['nullable', 'string', 'max:1000'],

            // التحقق من شبكة الأصناف الممررة بناءً على البنية العالمية الجديدة
            'items'                => ['required', 'array', 'min:1'],
            'items.*.item_id'      => ['required', 'integer', 'exists:items,id'],

            // التعديل المعماري: التحقق من وجود معرف سطر الوحدة في مصفوفة الأصناف ومنع تكرار نفس الوحدة لنفس الصنف
            'items.*.item_unit_id' => ['required', 'integer', 'exists:item_units,id', 'distinct'],

            'items.*.quantity'     => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost'    => ['required', 'numeric', 'gte:0'],
        ];
    }

    /**
     * تخصيص رسائل الخطأ لتظهر بلغة عربية محاسبية واضحة
     */
    public function messages(): array
    {
        return [
            'store_id.required'             => 'يجب تحديد المستودع المستهدف لحقن البضاعة الافتتاحية.',
            'store_id.exists'               => 'المستودع المحدد غير موجود بنظام اللوجستيات لدينا.',
            'opening_date.required'         => 'تاريخ إدخال الرصيد الافتتاحي إلزامي لتأريخ الحركات الجردية.',
            'items.required'                => 'لا يمكن حفظ مستند بضاعة أول المدة فارغاً بدون أسطر أصناف.',
            'items.*.item_id.required'      => 'معرف الصنف مطلوب في السطر.',
            'items.*.item_id.exists'        => 'الصنف المحدد غير موجود في شجرة الأصناف.',
            'items.*.item_unit_id.required' => 'يجب تحديد وحدة القياس الخاصة بالصنف في السطر.',
            'items.*.item_unit_id.exists'   => 'وحدة القياس الممررة غير معرفة في مصفوفة الصنف الحالي.',
            'items.*.item_unit_id.distinct' => 'تم تكرار وحدة الصنف في أكثر من سطر، يرجى تجميع الكميات لنفس الوحدة في سطر واحد.',
            'items.*.quantity.required'     => 'الكمية الجردية مطلوبة لكل سطر صنف.',
            'items.*.quantity.gt'           => 'يجب أن تكون الكمية الافتتاحية أكبر من الصفر الصريح.',
            'items.*.unit_cost.required'    => 'تكلفة الوحدة مطلوبة لاحتساب التقييم المخزني والقيد المالي.',
            'items.*.unit_cost.gte'         => 'تكلفة الصنف الافتتاحية لا يمكن أن تكون قيمة سالبة.',
        ];
    }
}
