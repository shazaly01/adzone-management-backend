<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class RefreshStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'store_id'   => ['required', 'integer', 'exists:stores,id'],
            'item_ids'   => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:items,id'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة لتناسب عمليات التحقق اللحظية
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'يجب تحديد المخزن أولاً لتحديث كميات المخزون اللحظية.',
            'store_id.exists'   => 'المخزن المحدد غير موجود في قاعدة بيانات النظام.',
            'item_ids.required' => 'مصفوفة الأصناف مطلوبة لتحديث كميات الشبكة الحالية.',
            'item_ids.array'    => 'يجب أن تكون الأصناف الممررة على هيئة مصفوفة صحيحة.',
            'item_ids.*.exists' => 'أحد الأصناف الممررة غير موجود في النظام.',
        ];
    }
}
