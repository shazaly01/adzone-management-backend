<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // سيتم تفعيل الصلاحية الفعلية عند ربط الـ Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // نحصل على الدور من المسار للتحقق من تفرد الاسم
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // تأكد من أن الاسم فريد، مع تجاهل الدور الحالي
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name,guard_name,api',
        ];
    }
}
