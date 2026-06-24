<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreasuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // تأمين استقبال الرصيد الافتتاحي للخزينة
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم الخزينة مطلوب ولا يمكن تركه فارغاً.',
            'name.string'              => 'يجب أن يكون اسم الخزينة نصاً صحيحاً.',
            'name.max'                 => 'اسم الخزينة طويل جداً، الحد الأقصى 255 حرفاً.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي للخزينة قيمة رقمية.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي للخزينة بالسالب.',
        ];
    }
}
