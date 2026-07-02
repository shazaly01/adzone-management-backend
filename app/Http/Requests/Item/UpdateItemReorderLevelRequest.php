<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemReorderLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // الصلاحيات يتم فحصها مركزياً داخل الـ Controller عبر الـ Policy تماشياً مع قواعد النظام
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id'      => ['required', 'integer', 'exists:stores,id'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required'      => 'عفواً، يجب تحديد المخزن المستهدف لتعديل حد الطلب.',
            'store_id.exists'        => 'المخزن المحدد غير موجود بالنظام.',
            'reorder_level.required' => 'يرجى إدخال قيمة حد الطلب الجديدة.',
            'reorder_level.numeric'  => 'يجب أن يكون حد الطلب عبارة عن قيمة رقمية.',
            'reorder_level.min'      => 'عفواً، لا يمكن أن يكون حد الطلب أقل من صفر.',
        ];
    }
}
