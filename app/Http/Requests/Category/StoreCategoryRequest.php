<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name'      => ['required', 'string', 'max:191', 'unique:categories,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التصنيف مطلوب ولا يمكن تركه فارغاً.',
            'name.string'   => 'يجب أن يكون اسم التصنيف نصاً صحيحاً.',
            'name.max'      => 'اسم التصنيف طويل جداً، الحد الأقصى 191 حرفاً.',
            'name.unique'   => 'اسم التصنيف هذا مسجل مسبقاً في النظام.',
            'parent_id.exists' => 'التصنيف الأب المختار غير موجود في قاعدة البيانات.',
        ];
    }
}
