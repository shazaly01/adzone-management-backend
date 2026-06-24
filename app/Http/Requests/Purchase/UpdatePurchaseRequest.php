<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لتعديل هذا المستند تحديداً بناءً على الـ Policy
     */
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $this->user()->can('update', $purchase);
    }

    /**
     * قواعد التحقق الصارمة للبيانات المحدثة لرأس وسطور المشتريات
     */
    public function rules(): array
    {
        return [
            // --- قواعد رأس الفاتورة ---
            'invoice_type'    => ['required', Rule::in(['purchase', 'return'])],
            'parent_id'       => [
                'nullable',
                Rule::requiredIf($this->invoice_type === 'return'),
                'exists:purchases,id'
            ],
            'store_id'        => ['required', 'exists:stores,id'],

            // [تحديث مالي معماري]: التحقق المشروط من الخزنة في حالة الدفع النقدي والتعديل الجاري
            'treasury_id'     => [
                'nullable',
                Rule::requiredIf($this->payment_type === 'cash'),
                'exists:treasuries,id'
            ],

            // [تحديث مالي معماري]: التحقق المشروط من البنك في حالة دفع الشبكة والتعديل الجاري
            'bank_id'         => [
                'nullable',
                Rule::requiredIf($this->payment_type === 'card'),
                'exists:banks,id'
            ],

            'supplier_id'     => ['required', 'exists:suppliers,id'],
            'invoice_date'    => ['required', 'date'],

            // [تحديث]: السماح بـ card ضمن الخيارات المعتمدة لطريقة السداد
            'payment_type'    => ['required', Rule::in(['cash', 'card', 'credit'])],

            'subtotal'         => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount'      => ['nullable', 'numeric', 'min:0'],
            'grand_total'     => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:1000'],

            // --- قواعد مصفوفة السطور المحدثة (Items) ---
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_id'        => ['required', 'exists:items,id'],

            // التعديل المعماري: التحقق من معرف سطر الوحدة من مصفوفة الصنف المستهدفة لمنع الاختلال
            'items.*.item_unit_id'   => ['required', 'exists:item_units,id', 'distinct'],

            'items.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost'      => ['required', 'numeric', 'min:0'],
            'items.*.subtotal'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount'=> ['nullable', 'numeric', 'min:0'],
            'items.*.grand_total'    => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * تخصيص أسماء الحقول لرسائل الخطأ العربية
     */
    public function attributes(): array
    {
        return [
            'invoice_type'             => 'نوع الفاتورة',
            'parent_id'                => 'الفاتورة الأصلية',
            'store_id'                 => 'المستودع',
            'treasury_id'              => 'الخزنة المالية',
            'bank_id'                  => 'الحساب البنكي',
            'supplier_id'              => 'المورد',
            'invoice_date'             => 'تاريخ الفاتورة',
            'payment_type'             => 'طريقة الدفع',
            'grand_total'              => 'الصافي النهائي',
            'items'                    => 'عناصر الفاتورة',
            'items.*.item_id'          => 'الصنف',
            'items.*.item_unit_id'     => 'وحدة الصنف',
            'items.*.quantity'         => 'الكمية',
            'items.*.unit_cost'        => 'سعر تكلفة الوحدة',
            'items.*.grand_total'      => 'إجمالي السطر',
        ];
    }
}
