<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'name'      => ['required', 'string', 'max:255'],
            'location'  => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * الحصول على رسائل الخطأ المخصصة بالعربية
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'اسم المخزن مطلوب لتعديل البيانات.',
            'name.string'       => 'يجب أن يكون اسم المخزن نصاً صحيحاً.',
            'name.max'          => 'اسم المخزن طويل جداً، الحد الأقصى 255 حرفاً.',
            'location.string'   => 'يجب أن يكون الموقع نصاً صحيحاً.',
            'location.max'      => 'الموقع طويل جداً، الحد الأقصى 255 حرفاً.',
            'is_active.required'=> 'حالة نشاط المخزن مطلوبة لتحديد إمكانية التوريد والصرف.',
            'is_active.boolean' => 'يجب أن تكون حالة النشاط (مفعل أو معطل) فقط.',
        ];
    }
}
