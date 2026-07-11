<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit') ? $this->route('unit')->id : $this->route('unit');

        return [
            'name'       => ['required', 'string', 'max:100', 'unique:units,name,' . $unitId],
            'short_name' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الوحدة مطلوب للتعديل.',
            'name.unique'   => 'اسم الوحدة هذا مستخدم لصنف آخر بالفعل.',
        ];
    }
}
