<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'   => 'required|string|max:255',
            'username'    => 'required|string|max:255|unique:users,username',
            'email'       => 'nullable|string|email|max:255',
            'password'    => ['required', 'confirmed', Password::defaults()],
            'roles'       => 'required|array',
            'roles.*'     => 'string|exists:roles,name,guard_name,api',
            'type'        => 'required|string|in:regular,designer,technician',

            // التحقق من صحة وجود المعرفات في الجداول الخاصة بها
            'store_id'    => 'nullable|exists:stores,id',
            'treasury_id' => 'nullable|exists:treasuries,id',
            'bank_id'     => 'nullable|exists:banks,id',
        ];
    }
}
