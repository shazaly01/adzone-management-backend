<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
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
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'tax_number'      => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // استقبال وتأمين الرصيد الافتتاحي المعدل للمورد
        ];
    }

    /**
     * Get the custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required'            => 'اسم المورد مطلوب لتعديل البيانات.',
            'name.string'              => 'يجب أن يكون اسم المورد نصاً صحيحاً.',
            'name.max'                 => 'اسم المورد طويل جداً، الحد الأقصى 255 حرفاً.',
            'phone.max'                => 'رقم الهاتف طويل جداً، الحد الأقصى 50 حرفاً.',
            'tax_number.max'           => 'الرقم الضريبي طويل جداً، الحد الأقصى 50 حرفاً.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي المعدل قيمة رقمية.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي المعدل بالسالب.',
        ];
    }
}
