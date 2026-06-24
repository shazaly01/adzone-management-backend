<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Account;
use App\Models\User;

class StoreVoucherRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لإنشاء السند المالي
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Voucher::class);
    }

    /**
     * [حقن معماري ذكي]: اعتراض البيانات قبل الفحص لحل مشكلة الحساب المساعد للمصممين تلقائياً
     */
    protected function prepareForValidation()
    {
        // إذا كان نوع الحساب المساعد مصمم، نقوم بدمج معرف حسابه التجميعي (2103) آلياً دون تدخّل الواجهة
        if (in_array($this->input('sub_ledger_type'), ['designer', 'user', User::class])) {
            $account = Account::where('code', Account::CODE_DESIGNERS_LEDGER)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }
    }

    /**
     * قواعد التحقق الصارمة لسندات الصرف والقبض المحدثة
     */
    public function rules(): array
    {
        return [
            'voucher_type'    => ['required', Rule::in(['payment', 'receipt'])],
            'account_id'      => ['required', 'exists:accounts,id'],

            // [تعديل جوهري]: السماح بقبول الكيان 'designer' و 'App\Models\User' ضمن الكيانات المعتمدة
            'sub_ledger_type' => ['required', 'string', Rule::in(['supplier', 'customer', 'expense', 'designer', User::class])],
            'sub_ledger_id'   => ['required', 'integer'],

            'payment_method'  => ['required', Rule::in(['cash', 'bank'])],
            'fund_account_id' => ['required', 'exists:accounts,id'], // الحساب التجميعي الرئيسي للخزائن أو البنوك

            // التحقق الشرطي المحمي للخزنة التحليلية
            'treasury_id'     => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('payment_method') === 'cash'),
                'exists:treasuries,id'
            ],

            // التحقق الشرطي المحمي للبنك التحليلي
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
     * تخصيص أسماء الحقول لتظهر الأخطاء واضحة للمستخدم باللغة العربية
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
