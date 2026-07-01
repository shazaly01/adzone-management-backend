<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Account;
use App\Models\User;

class UpdateVoucherRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لتعديل السند المالي بناءً على الـ Policy
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('voucher'));
    }

    /**
     * [حقن معماري ذكي]: اعتراض البيانات قبل الفحص لحل مشكلة الحسابات المساعدة تلقائياً عند التعديل
     */
    protected function prepareForValidation()
    {
        // إذا كان نوع الحساب المساعد مصمم، نقوم بدمج معرف حسابه التجميعي (2103) آلياً
        if (in_array($this->input('sub_ledger_type'), ['designer', 'user', User::class])) {
            $account = Account::where('code', Account::CODE_DESIGNERS_LEDGER)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }

        // إذا كان نوع الحساب المساعد بنك، نقوم بدمج معرف حسابه التجميعي الرئيسي (1102) آلياً
        if ($this->input('sub_ledger_type') === 'bank') {
            $account = Account::where('code', Account::CODE_BANKS)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }

        // إذا كان نوع الحساب المساعد خزينة، نقوم بدمج معرف حسابه التجميعي الرئيسي (1101) آلياً
        if ($this->input('sub_ledger_type') === 'treasury') {
            $account = Account::where('code', Account::CODE_TREASURY)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }
    }

    /**
     * قواعد التحقق عند تعديل السند (الحفاظ على نفس صرامة القيود المالية المشروطة)
     */
    public function rules(): array
    {
        return [
            'voucher_type'    => ['required', Rule::in(['payment', 'receipt'])],
            'account_id'      => ['required', 'exists:accounts,id'],

            // [تعديل جوهري]: مزامنة الكيانات المسموحة لتشمل 'bank', 'treasury', 'designer' أثناء التحديث
            'sub_ledger_type' => ['required', 'string', Rule::in(['supplier', 'customer', 'expense', 'designer', 'bank', 'treasury', User::class])],
            'sub_ledger_id'   => ['required', 'integer'],

            'payment_method'  => ['required', Rule::in(['cash', 'bank'])],
            'fund_account_id' => ['required', 'exists:accounts,id'],

            // [إصلاح معماري]: استخدام الدالة السهمية لحماية الخزنة أثناء التعديل العكسي للقيود
            'treasury_id'     => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('payment_method') === 'cash'),
                'exists:treasuries,id'
            ],

            // [إصلاح معماري]: استخدام الدالة السهمية لحماية البنك أثناء التعديل العكسي للقيود
            'bank_id'         => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('payment_method') === 'bank'),
                'exists:banks,id'
            ],

            'amount'          => ['required', 'numeric', 'gt:0'],
            'voucher_date'    => ['required', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * تخصيص أسماء الحقول للغة العربية
     */
    public function attributes(): array
    {
        return [
            'voucher_type'    => 'نوع السند المالي',
            'account_id'      => 'الحساب المستهدف الرئيسي',
            'sub_ledger_type' => 'نوع الحساب المساعد',
            'sub_ledger_id'   => 'الجهة أو الحساب المساعد الفرعي',
            'payment_method'  => 'طريقة الدفع',
            'fund_account_id' => 'حساب الصندوق أو البنك التجميعي',
            'treasury_id'     => 'الخزنة الفرعية التحليلية',
            'bank_id'         => 'الحساب البنكي الفرعي التحليلي',
            'amount'          => 'المبلغ المالي',
            'voucher_date'    => 'تاريخ السند',
            'notes'           => 'البيان والشرح',
        ];
    }
}
