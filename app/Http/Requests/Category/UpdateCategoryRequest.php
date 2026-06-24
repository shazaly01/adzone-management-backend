<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category') ? $this->route('category')->id : $this->route('category');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                'not_in:' . $categoryId
            ],
            'name'      => ['required', 'string', 'max:191', 'unique:categories,name,' . $categoryId],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'اسم التصنيف مطلوب ولا يمكن تركه فارغاً.',
            'name.unique'       => 'اسم التصنيف هذا مسجل مسبقاً في النظام.',
            'parent_id.exists'  => 'التصنيف الأب المختار غير موجود.',
            'parent_id.not_in'  => 'خطأ هندسي: لا يمكن تعيين التصنيف ليكون أباً لنفسه.',
        ];
    }
}
