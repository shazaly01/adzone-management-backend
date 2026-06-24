<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'email'           => ['nullable', 'email', 'max:255'],
            'credit_limit'    => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'], // استقبال وتأمين الرصيد الافتتاحي للعميل الجديد
        ];
    }

    /**
     * Get the custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required'            => 'اسم العميل مطلوب وإلزامي.',
            'name.string'              => 'يجب أن يكون اسم العميل نصاً صحيحاً.',
            'name.max'                 => 'اسم العميل طويل جداً، الحد الأقصى 255 حرفاً.',
            'phone.max'                => 'رقم الهاتف طويل جداً، الحد الأقصى 50 حرفاً.',
            'email.email'              => 'صيغة البريد الإلكتروني المدخل غير صالحة.',
            'email.max'                => 'البريد الإلكتروني طويل جداً، الحد الأقصى 255 حرفاً.',
            'credit_limit.numeric'     => 'يجب أن يكون الحد الائتماني قيمة رقمية صحيحة.',
            'credit_limit.min'         => 'لا يمكن أن يكون الحد الائتماني أقل من صفر.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي للعميل قيمة رقمية.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي للعميل بالسالب.',
        ];
    }
}
