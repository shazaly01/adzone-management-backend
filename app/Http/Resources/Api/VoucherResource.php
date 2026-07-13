<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * تحويل بيانات السند المالي الموحد إلى JSON المفرود والكامل للواجهة الأمامية للطباعة والعرض
     */
    public function toArray(Request $request): array
    {
        // طريقة النقدية وحساب الصندوق أو البنك التجميعي المقابل
        $fundAccountName = $this->payment_method === 'cash'
            ? ($this->treasury->name ?? $this->fundAccount->name ?? null)
            : ($this->bank->name ?? $this->fundAccount->name ?? null);

        return [
            'id'                 => $this->id,
            'voucher_type'       => $this->voucher_type,
            'voucher_type_lbl'   => $this->voucher_type === 'payment' ? 'سند صرف مالي' : 'سند قبض مالي',
            'voucher_sequence'   => $this->voucher_sequence,
            'voucher_number'     => $this->voucher_number,

            // الحساب المستهدف: يظهر اسم الحساب المساعد الفعلي بدلاً من الاسم التجميعي الإجمالي
            'account_id'         => $this->account_id,
            'account_name'       => $this->subLedger->name ?? $this->account->name ?? null,

            // [تعديل جوهري]: تنظيف اسم الكلاس وإرجاع الاسم الصغير للفرونت إند (مثل customer) لحماية الشروط والأيقونات
            'sub_ledger_type'    => $this->sub_ledger_type ? strtolower(class_basename($this->sub_ledger_type)) : null,
            'sub_ledger_id'      => $this->sub_ledger_id,
            'sub_ledger_name'    => $this->subLedger->name ?? null,

            // طريقة النقدية والوسيط المالي
            'payment_method'     => $this->payment_method,
            'payment_method_lbl' => $this->payment_method === 'cash' ? 'نقدي' : 'بنكي',
            'fund_account_id'    => $this->fund_account_id,
            'fund_account_name'  => $fundAccountName,

            // الحسابات التحليلية الفرعية لتوثيق الطباعة
            'treasury_id'        => $this->treasury_id,
            'treasury_name'      => $this->treasury->name ?? null,

            'bank_id'            => $this->bank_id,
            'bank_name'          => $this->bank->name ?? null,

            // البيانات المالية والزمنية الصافية
            'amount'             => (float) $this->amount,
            'voucher_date'       => $this->voucher_date ? $this->voucher_date->format('Y-m-d H:i:s') : null,
            'notes'              => $this->notes,

            // روابط منشئ السند وقيد اليومية الناتج
            'user_id'            => $this->user_id,
            'user_name'          => $this->user->full_name ?? $this->user->name ?? null,
            'journal_entry_id'   => $this->journal_entry_id,
            'journal_entry_no'   => $this->journalEntry->entry_number ?? null,
        ];
    }
}
