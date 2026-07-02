<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    /**
     * تحديد صلاحية تمرير الطلب (تتم إدارتها مركزيّاً عبر الـ Policies والـ Middleware)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من تواريخ فلترة لوحة التحكم
     */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }
}
