<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // البيانات الأساسية للصنف
            'name'                               => ['required', 'string', 'max:255'],
            'item_type'                          => ['required', 'in:product,service,raw_material'],
            'profit_margin'                      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category_id'                        => ['nullable', 'exists:categories,id'],
            'category_path'                      => ['nullable', 'string'],
            'base_unit_id'                       => ['required', 'exists:units,id'],

            // [تعديل التوافق]: إضافة استقبال حقل الحالة لتجنب سقوطه عند الفلترة بـ validated()
            'is_active'                          => ['required', 'boolean'],
            'is_dimensional'                                     => ['required', 'boolean'],

            // مصفوفة الوحدات المتعددة (اللانهائية)
            'units'                              => ['required', 'array', 'min:1'],
            'units.*.unit_id'                    => ['required', 'exists:units,id'],
            'units.*.conversion_factor'          => ['required', 'numeric', 'min:0.0001'],
            'units.*.cost'                       => ['required', 'numeric', 'min:0'],
            'units.*.price'                      => ['required', 'numeric', 'min:0'],

            // مصفوفة الباركودات التابعة لكل وحدة
            'units.*.barcodes'                   => ['nullable', 'array'],
            'units.*.barcodes.*'                 => ['required', 'string', 'unique:item_barcodes,barcode'],

            // مصفوفة فئات الأسعار والخصومات التابعة لكل وحدة (Pricing Matrix)
            'units.*.prices'                     => ['nullable', 'array'],
            'units.*.prices.*.price_list_id'     => ['required', 'exists:price_lists,id'],
            'units.*.prices.*.discount_percentage'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'units.*.prices.*.price'             => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة لتناسب المصفوفات الديناميكية
     */
    public function messages(): array
    {
        return [
            'name.required'                          => 'اسم الصنف أو الخدمة مطلوب ولا يمكن تركه فارغاً.',
            'item_type.required'                     => 'طبيعة الصنف مطلوبة (منتج، خدمة، أو مادة خام).',
            'item_type.in'                           => 'طبيعة الصنف الممررة غير صحيحة.',
            'profit_margin.numeric'                  => 'يجب أن تكون نسبة الربح قيمة رقمية.',
            'profit_margin.min'                      => 'لا يمكن أن تكون نسبة الربح أقل من 0%.',
            'profit_margin.max'                      => 'لا يمكن أن تتجاوز نسبة الربح 100%.',
            'category_id.exists'                     => 'التصنيف المختار غير موجود في النظام.',
            'base_unit_id.required'                  => 'يجب تحديد الوحدة القياسية الصغرى للصنف.',
            'base_unit_id.exists'                    => 'الوحدة الأساسية المختارة غير موجودة بدليل الوحدات.',
            'is_active.required'                     => 'حالة تفعيل أو إيقاف الصنف مطلوبة وبنية ممررة.',

            // الوحدات اللانهائية
            'units.required'                         => 'يجب إدراج وحدة قياس واحدة على الأقل للصنف.',
            'units.*.unit_id.required'               => 'تحديد الوحدة مطلوب.',
            'units.*.unit_id.exists'                 => 'وحدة القياس المدرجة غير موجودة بدليل الوحدات.',
            'units.*.conversion_factor.required'     => 'معامل التحويل مطلوب لكل وحدة مضافة.',
            'units.*.conversion_factor.numeric'      => 'يجب أن يكون معامل التحويل رقماً صحيحاً أو عشرياً.',
            'units.*.conversion_factor.min'          => 'لا يمكن أن يكون معامل التحويل صفراً أو أقل.',
            'units.*.cost.required'                  => 'سعر التكلفة مطلوب للوحدة المدرجة.',
            'units.*.cost.numeric'                   => 'يجب أن يكون سعر التكلفة قيمة رقمية.',
            'units.*.price.required'                 => 'سعر البيع الافتراضي مطلوب للوحدة المدرجة.',
            'units.*.price.numeric'                  => 'يجب أن يكون سعر البيع قيمة رقمية.',

            // الباركودات اللانهائية
            'units.*.barcodes.*.required'            => 'قيمة الباركود لا يمكن أن تكون فارغة.',
            'units.*.barcodes.*.unique'              => 'هذا الباركود مستخدم مسبقاً مع صنف أو وحدة أخرى في النظام.',

            // مصفوفة فئات الأسعار
            'units.*.prices.*.price_list_id.required'=> 'فئة السعر (مثل: جملة، جمهور) مطلوبة.',
            'units.*.prices.*.price_list_id.exists'  => 'فئة السعر المحددة غير موجودة بالنظام.',
            'units.*.prices.*.discount_percentage.min'=> 'نسبة الخصم لفئة السعر لا يمكن أن تقل عن 0%.',
            'units.*.prices.*.discount_percentage.max'=> 'نسبة الخصم لفئة السعر لا يمكن أن تتجاوز 100%.',
            'units.*.prices.*.price.required'        => 'السعر الفعلي النهائي لهذه الفئة مطلوب.',
        ];
    }
}
