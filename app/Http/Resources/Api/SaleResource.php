<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Sale;

class SaleResource extends JsonResource
{
    /**
     * تحويل بيانات رأس فاتورة المبيعات مع تضمين السطور التابعة لها إلى JSON جاهز للطباعة والعرض
     */
    public function toArray(Request $request): array
    {
        // مصفوفة التسميات المحدثة لضمان معالجة خيار الدفع الإلكتروني (card) جذرياً بدون ترقيع
        $paymentTypeLabels = [
            'cash'   => 'نقدي',
            'card'   => 'شبكة / دفع إلكتروني',
            'credit' => 'آجل / ذمم',
        ];

        // مصفوفة تسميات الحالات التشغيلية المحدثة والخاصة بورشتك
        $productionStatusLabels = [
            Sale::STATUS_PENDING    => 'قيد الانتظار',
            Sale::STATUS_PROCESSING => 'جاري التشغيل',
            Sale::STATUS_ON_HOLD    => 'معلق',
            Sale::STATUS_COMPLETED  => 'تم التنفيذ بالكامل',
        ];

        // مصفوفة ألوان الحالات (Hex Codes) المتوافقة هندسياً مع النمط الداكن والعنبري لواجهتك
        $productionStatusColors = [
            Sale::STATUS_PENDING    => '#f59e0b', // أمبر / أصفر (تنبيه)
            Sale::STATUS_PROCESSING => '#3b82f6', // أزرق (جاري العمل)
            Sale::STATUS_ON_HOLD    => '#ef4444', // أحمر (معلق/متوقف)
            Sale::STATUS_COMPLETED  => '#10b981', // أخضر (مكتمل)
        ];

        return [
            'id'               => $this->id,
            'invoice_type'     => $this->invoice_type,
            'invoice_type_lbl' => $this->invoice_type === 'sale' ? 'فاتورة مبيعات' : 'مردودات مبيعات',
            'invoice_sequence' => $this->invoice_sequence,
            'invoice_number'   => $this->invoice_number, // الكود النظيف المنسق (INV-0001 أو SR-0001)
            'parent_id'        => $this->parent_id,
            'parent_number'    => $this->parentInvoice->invoice_number ?? null, // رقم الفاتورة الأصلية في حال المرتجع

            // روابط الحسابات والقنوات اللوجستية والمالية
            'store_id'         => $this->store_id,
            'store_name'       => $this->store->name ?? null,

            'treasury_id'      => $this->treasury_id,
            'treasury_name'    => $this->treasury->name ?? null, // اسم الخزنة المستلمة ماليّاً (للمحاسب داخلياً)

            'bank_id'          => $this->bank_id,
            'bank_name'        => $this->bank->name ?? null, // اسم الحساب البنكي المستلم (للمحاسب داخلياً)

            'sale_type'          => $this->sale_type,
            'customer_name_text' => $this->customer_name_text,
            'customer_id'      => $this->customer_id,
            'customer_name'    => $this->customer->name ?? null, // اسم العميل المستخرج من شجرة الحسابات
            'user_id'          => $this->user_id,
            'user_name'        => $this->user->name ?? null, // كاتب الفاتورة / الكاشير

            // البيانات المالية والزمنية للفاتورة
            'invoice_date'     => $this->invoice_date ? $this->invoice_date->format('Y-m-d H:i:s') : null,
            'payment_type'     => $this->payment_type,
            'payment_type_lbl' => $paymentTypeLabels[$this->payment_type] ?? $this->payment_type, // المسمى العام الذي يطبع للعميل

            'subtotal'         => (float) $this->subtotal,
            'discount_amount'  => (float) $this->discount_amount,
            'tax_amount'       => (float) $this->tax_amount,
            'grand_total'      => (float) $this->grand_total, // الصافي النهائي المراد طباعته
            'notes'            => $this->notes,

            // بيانات المصمم المسؤول وعمولته المحدثة
            'designer_id'          => $this->designer_id,
            'designer_name'        => $this->designer->name ?? null, // جلب اسم المصمم للواجهة
            'designer_meter_price' => $this->designer_meter_price !== null ? (float) $this->designer_meter_price : null,
            'design_commission'    => $this->design_commission !== null ? (float) $this->design_commission : null,

            // [إضافة بروتوكول التشخيص المطور]: الحقول التشغيلية لورشة التنفيذ وفنيي الطباعة
            'production_status'       => $this->production_status,
            'production_status_lbl'   => $productionStatusLabels[$this->production_status] ?? $this->production_status,
            'production_status_color' => $productionStatusColors[$this->production_status] ?? '#f59e0b',

            // تحميل سطور تفاصيل الفاتورة تلقائياً عبر ريسورس السطور الوسيط عند استدعائه بالـ Eager Loading
            'items'            => SaleItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
