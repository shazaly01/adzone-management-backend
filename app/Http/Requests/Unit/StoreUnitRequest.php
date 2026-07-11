<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100', 'unique:units,name'],
            'short_name' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الوحدة مطلوب (مثال: حبة، كرتون).',
            'name.unique'   => 'اسم الوحدة هذا مسجل مسبقاً بدليل الوحدات.',
            'name.max'      => 'اسم الوحدة طويل جداً.',
        ];
    }
}
