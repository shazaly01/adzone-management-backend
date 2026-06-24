<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * تحويل بيانات رأس فاتورة المشتريات مع تضمين السطور التابعة لها إلى JSON
     */
    public function toArray(Request $request): array
    {
        // مصفوفة التسميات المحدثة لضمان معالجة خيار الدفع الإلكتروني (card) جذرياً بدون ترقيع
        $paymentTypeLabels = [
            'cash'   => 'نقدي',
            'card'   => 'شبكة / دفع إلكتروني',
            'credit' => 'آجل / ذمم',
        ];

        return [
            'id'               => $this->id,
            'invoice_type'     => $this->invoice_type,
            'invoice_type_lbl' => $this->invoice_type === 'purchase' ? 'فاتورة مشتريات' : 'مرتجع مشتريات',
            'invoice_sequence' => $this->invoice_sequence,
            'invoice_number'   => $this->invoice_number, // الكود النظيف المنسق (PUR-0001)
            'parent_id'        => $this->parent_id,
            'parent_number'    => $this->parentInvoice->invoice_number ?? null, // رقم الفاتورة الأصلية في حال المرتجع

            // البيانات اللوجستية وروابط الحسابات والمالية المحدثة
            'store_id'         => $this->store_id,
            'store_name'       => $this->store->name ?? null,

            'treasury_id'      => $this->treasury_id,
            'treasury_name'    => $this->treasury->name ?? null, // اسم الخزنة الصارفة ماليّاً للسيولة النقدية

            'bank_id'          => $this->bank_id,
            'bank_name'        => $this->bank->name ?? null, // اسم الحساب البنكي الصارف للحركة الإلكترونية

            'supplier_id'      => $this->supplier_id,
            'supplier_name'    => $this->supplier->name ?? null, // اسم المورد المستخرج من شجرة الحسابات (الذمم الدائنة)

            'user_id'          => $this->user_id,
            'user_name'        => $this->user->name ?? null,

            // البيانات المالية والزمنية
            'invoice_date'     => $this->invoice_date->format('Y-m-d H:i:s'),
            'payment_type'     => $this->payment_type,
            'payment_type_lbl' => $paymentTypeLabels[$this->payment_type] ?? $this->payment_type,

            'subtotal'         => (float) $this->subtotal,
            'discount_amount'  => (float) $this->discount_amount,
            'tax_amount'       => (float) $this->tax_amount,
            'grand_total'      => (float) $this->grand_total,
            'notes'            => $this->notes,

            // تحميل سطور وتفاصيل الفاتورة تلقائياً عبر الريسورس الوسيط عند استدعائه
            'items'            => PurchaseItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
