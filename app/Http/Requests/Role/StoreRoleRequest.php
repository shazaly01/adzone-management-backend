<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // سيتم تفعيل الصلاحية الفعلية عند ربط الـ Policy
        // حاليًا، نفترض أن المستخدم الذي يصل إلى المسار مصرح له
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            // التحقق من أن كل صلاحية مرسلة هي عبارة عن اسم موجود في جدول permissions
            // وتتبع الحارس 'api'
            'permissions.*' => 'string|exists:permissions,name,guard_name,api',
        ];
    }
}
