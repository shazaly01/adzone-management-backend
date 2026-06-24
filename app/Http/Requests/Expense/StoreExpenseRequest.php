<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم بند المصروف (مثل: فواتير مياه، صيانة) مطلوب ولا يمكن تركه فارغاً.',
            'name.string'   => 'يجب أن يكون اسم بند المصروف نصاً تعبيرياً صحيحاً.',
            'name.max'      => 'اسم بند المصروف طويل جداً، الحد الأقصى 255 حرفاً.',
        ];
    }
}
