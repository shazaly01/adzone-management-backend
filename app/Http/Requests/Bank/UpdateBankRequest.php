<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
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
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // السماح بتعديل الرصيد الافتتاحي لتشغيل استراتيجية الحذف وإعادة البناء
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم البنك مطلوب للحفظ التعديل.',
            'name.string'              => 'يجب أن يكون اسم البنك نصاً صحيحاً.',
            'account_number.max'       => 'رقم الحساب البنكي طويل جداً.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي المعدل قيمة رقمية.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي المعدل بالسالب.',
        ];
    }
}
