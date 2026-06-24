<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'account_number'  => ['nullable', 'string', 'max:50'],
            'iban'            => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // إضافة حقل الرصيد الافتتاحي وتأمينه برمجياً
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم البنك مطلوب وإلزامي.',
            'name.string'              => 'يجب أن يكون اسم البنك نصاً صحيحاً.',
            'name.max'                 => 'اسم البنك طويل جداً، الحد الأقصى 255 حرفاً.',
            'account_number.max'       => 'رقم الحساب البنكي طويل جداً، الحد الأقصى 50 حرفاً.',
            'iban.max'                 => 'رقم الآيبان طويل جداً، الحد الأقصى 50 حرفاً.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي قيمة رقمية صحيحة.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي بالسالب.',
        ];
    }
}
