<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Item;

class UpdateSaleRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لتحديث المستند بناءً على السياسات الأمنية للـ API
     */
    public function authorize(): bool
    {
        $sale = $this->route('sale');
        return $this->user()->can('update', $sale);
    }

    /**
     * قواعد التحقق الصارمة لتحديث رأس وسطور المبيعات
     */
    public function rules(): array
    {
        return [
            // --- قواعد رأس الفاتورة ---
            'invoice_type'    => ['required', Rule::in(['sale', 'return'])],
            'parent_id'       => [
                'nullable',
                Rule::requiredIf($this->invoice_type === 'return'),
                'exists:sales,id'
            ],
            'store_id'        => ['required', 'exists:stores,id'],

            // حقن واشتراط الخزنة ماليّاً فقط في حال كان الدفع نقداً
            'treasury_id'     => [
                Rule::requiredIf($this->payment_type === 'cash'),
                'nullable',
                'exists:treasuries,id'
            ],

            // حقن واشتراط الحساب البنكي ماليّاً فقط في حال كان الدفع شبكة
            'bank_id'         => [
                Rule::requiredIf($this->payment_type === 'card'),
                'nullable',
                'exists:banks,id'
            ],

            'customer_id'     => ['required', 'exists:customers,id'],
            'invoice_date'    => ['required', 'date'],

            // تحديث طريقة الدفع لتقبل نوع الشبكة/الدفع الإلكتروني (card)
            'payment_type'    => ['required', Rule::in(['cash', 'card', 'credit'])],

            'subtotal'        => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount'      => ['nullable', 'numeric', 'min:0'],
            'grand_total'     => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'designer_id'          => ['nullable', Rule::exists('users', 'id')->where('type', 'designer')],
            'designer_meter_price' => ['nullable', 'numeric', 'min:0'],
            'design_commission'    => ['nullable', 'numeric', 'min:0'],
            'sale_type'            => ['required', Rule::in(['indoor', 'outdoor'])],
            'customer_name_text'   => ['nullable', 'string', 'max:255'],

            // --- قواعد مصفوفة السطور (Items) بناءً على معمارية الوحدات المحدثة ---
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_id'        => ['required', 'exists:items,id'],
            'items.*.item_unit_id'   => ['required', 'exists:item_units,id'],
            'items.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.subtotal'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount'=> ['nullable', 'numeric', 'min:0'],
            'items.*.grand_total'    => ['required', 'numeric', 'min:0'],
            'items.*.length'         => ['nullable', 'numeric', 'min:0'],
            'items.*.width'          => ['nullable', 'numeric', 'min:0'],
            'items.*.is_designed'    => ['required', 'boolean'],
        ];
    }

    /**
     * التحقق المتقدم بعد القواعد الأساسية لفرض الطول والعرض للأصناف المترية عند التعديل
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (empty($items) || !is_array($items)) {
                return;
            }

            // جلب كافة معرفات الأصناف الفريدة لتجنب الاستعلام المتكرر داخل الحلقة N+1
            $itemIds = collect($items)->pluck('item_id')->filter()->unique()->toArray();

            // تحديد الأصناف المعرفة كأصناف مترية ذات أبعاد في قاعدة البيانات
            $dimensionalItemIds = Item::whereIn('id', $itemIds)
                ->where('is_dimensional', true)
                ->pluck('id')
                ->toArray();

            // فحص السطور وإطلاق الأخطاء المخصصة بدقة لمنع التلاعب بالمقاسات أثناء التحديث
            foreach ($items as $index => $item) {
                $itemId = $item['item_id'] ?? null;

                if (in_array($itemId, $dimensionalItemIds)) {
                    $length = $item['length'] ?? null;
                    $width = $item['width'] ?? null;

                    if ($length === null || !is_numeric($length) || $length <= 0) {
                        $validator->errors()->add("items.{$index}.length", "حقل الطول مطلوب ويجب أن يكون أكبر من صفر لأن هذا الصنف متري الأبعاد.");
                    }

                    if ($width === null || !is_numeric($width) || $width <= 0) {
                        $validator->errors()->add("items.{$index}.width", "حقل العرض مطلوب ويجب أن يكون أكبر من صفر لأن هذا الصنف متري الأبعاد.");
                    }
                }
            }
        });
    }

    /**
     * تخصيص أسماء الحقول لرسائل الخطأ العربية الواضحة
     */
    public function attributes(): array
    {
        return [
            'invoice_type'             => 'نوع الفاتورة',
            'parent_id'                => 'فاتورة المبيعات الأصلية',
            'store_id'                 => 'المخزن',
            'treasury_id'              => 'الخزنة المالية المستلمة',
            'bank_id'                  => 'الحساب البنكي المستلم',
            'customer_id'              => 'حساب العميل',
            'invoice_date'             => 'تاريخ الفاتورة',
            'payment_type'             => 'طريقة الدفع',
            'grand_total'              => 'الصافي النهائي للمبيعات',
            'items'                    => 'عناصر الفاتورة',
            'items.*.item_id'          => 'الصنف المباع',
            'items.*.item_unit_id'     => 'وحدة الصنف',
            'items.*.quantity'         => 'الكمية المباعة',
            'items.*.unit_price'       => 'سعر البيع للوحدة',
            'items.*.grand_total'      => 'إجمالي السطر',
            'designer_id'              => 'المصمم المسؤول',
            'designer_meter_price'     => 'سعر المتر للمصمم',
            'design_commission'        => 'إجمالي عمولة التصميم',
            'sale_type'                => 'نوع حركة البيع (داخلي / خارجي)',
'customer_name_text'       => 'اسم العميل الخارجي (النصي)',
            'items.*.is_designed'      => 'خاضع للتصميم في السطر',
            'items.*.length'           => 'الطول للسطر',
            'items.*.width'            => 'العرض للسطر',
        ];
    }
}
