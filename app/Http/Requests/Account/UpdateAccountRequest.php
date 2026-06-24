<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // جلب معرف الحساب الحالي من المسار (Route Parameter) لتخطي شرط الـ Unique أثناء التحديث
        $accountId = $this->route('account');

        return [
            'name'            => ['required', 'string', 'max:255'],
            'parent_id'       => ['nullable', 'exists:accounts,id'],
            'type'            => ['required', 'string', Rule::in(['cash', 'bank', 'customer', 'supplier', 'expense', 'income', 'normal'])],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'اسم الحساب المالي مطلوب عند التعديل.',
            'name.string'              => 'يجب أن يكون اسم الحساب نصاً صحيحاً.',
            'name.max'                 => 'اسم الحساب طويل جداً، الحد الأقصى 255 حرفاً.',
            'parent_id.exists'         => 'الحساب الأب المختار غير موجود في شجرة الحسابات.',
            'type.required'            => 'نوع الحساب مطلوب.',
            'type.in'                  => 'نوع الحساب المختار غير صالح.',
            'opening_balance.numeric'  => 'يجب أن يكون الرصيد الافتتاحي قيمة رقمية صحيحة.',
            'opening_balance.min'      => 'لا يمكن أن يكون الرصيد الافتتاحي أقل من صفر.',
        ];
    }
}
