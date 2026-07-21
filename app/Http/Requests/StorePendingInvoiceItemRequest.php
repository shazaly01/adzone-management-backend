<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePendingInvoiceItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // تم ضبطها على true مباشرة لتخطي تعقيدات البوليسي بناءً على رغبتك
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
            'raw_text' => 'required|string|min:3',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'raw_text.required' => 'النص الخام مطلوب لمعالجته وتحليله.',
            'raw_text.string'   => 'يجب أن يكون المدخل نصاً صحيحاً.',
            'raw_text.min'      => 'يجب أن لا يقل النص عن 3 حروف لضمان إمكانية التحليل.',
        ];
    }
}
