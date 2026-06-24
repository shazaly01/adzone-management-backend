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
        return [
            'id'                 => $this->id,
            'voucher_type'       => $this->voucher_type,
            'voucher_type_lbl'   => $this->voucher_type === 'payment' ? 'سند صرف مالي' : 'سند قبض مالي',
            'voucher_sequence'   => $this->voucher_sequence,
            'voucher_number'     => $this->voucher_number, // PAY-0001 أو REC-0001

            // الحساب المستهدف (المصروف أو الإيراد الأساسي في شجرة الحسابات)
            'account_id'         => $this->account_id,
            'account_name'       => $this->account->name ?? null,

            // الحساب المساعد التابع للأستاذ العام (عميل، مورد...) إن وجد للتدقيق الخارجي
            'sub_ledger_type'    => $this->sub_ledger_type,
            'sub_ledger_id'      => $this->sub_ledger_id,
            'sub_ledger_name'    => $this->sub_ledger->name ?? null,

            // طريقة النقدية وحساب الصندوق أو البنك التجميعي المقابل
            'payment_method'     => $this->payment_method,
            'payment_method_lbl' => $this->payment_method === 'cash' ? 'نقدي' : 'بنكي',
            'fund_account_id'    => $this->fund_account_id,
            'fund_account_name'  => $this->fundAccount->name ?? null,

            // [حقن مضاف ومحدث]: تمرير أسماء الصناديق والبنوك التحليلية الفرعية لطباعتها قانونياً للعميل والمورد
            'treasury_id'        => $this->treasury_id,
            'treasury_name'      => $this->treasury->name ?? null, // مثال: خزينة الكاشير الرئيسية

            'bank_id'            => $this->bank_id,
            'bank_name'          => $this->bank->name ?? null, // مثال: حساب مصرف الراجحي

            // البيانات المالية والزمنية الصافية
            'amount'             => (float) $this->amount,
            'voucher_date'       => $this->voucher_date ? $this->voucher_date->format('Y-m-d H:i:s') : null,
            'notes'              => $this->notes, // البيان / الغرض من الصرف أو القبض

            // روابط المنظومة والموظف المنشئ
            'user_id'            => $this->user_id,
            'user_name'          => $this->user->full_name ?? $this->user->name ?? null,
            'journal_entry_id'   => $this->journal_entry_id,
            'journal_entry_no'   => $this->journalEntry->entry_number ?? null,
        ];
    }
}
