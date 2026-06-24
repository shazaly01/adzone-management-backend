<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'اسم بند المصروف مطلوب عند التعديل.',
            'name.string'       => 'يجب أن يكون اسم البند نصاً صحيحاً.',
            'is_active.required'=> 'تحديد حالة تنشيط البند مطلوبة لمنع أو إتاحة الصرف عليه.',
        ];
    }
}
