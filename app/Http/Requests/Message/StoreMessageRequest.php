<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية تتم عبر الـ Policy والـ Middleware
    }

    public function rules(): array
    {
        return [
            'content'        => 'required|string|max:500',
            'beneficiary_id' => 'nullable|exists:beneficiaries,id',
            'area_id'        => 'nullable|exists:areas,id',
            'type'           => 'required|in:individual,area',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'نص الرسالة مطلوب.',
            'type.required'    => 'نوع الإرسال مطلوب.',
            'beneficiary_id.exists' => 'المستفيد المختار غير موجود.',
            'area_id.exists'        => 'المنطقة المختارة غير موجودة.',
        ];
    }
}
