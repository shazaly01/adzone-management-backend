<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTreasuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // استقبال وتأمين تعديل الرصيد الافتتاحي المضمن
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم الخزينة مطلوب لتعديل البيانات.',
            'name.string'              => 'يجب أن يكون اسم الخزينة نصاً صحيحاً.',
            'name.max'                 => 'اسم الخزينة طويل جداً، الحد الأقصى 255 حرفاً.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي المعدل قيمة رقمية.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي المعدل بالسالب.',
        ];
    }
}
