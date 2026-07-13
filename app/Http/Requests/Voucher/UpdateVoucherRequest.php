<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Account;
use App\Models\User;

class UpdateVoucherRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لتعديل هذا السند المالي المحدد
     */
    public function authorize(): bool
    {
        $voucher = $this->route('voucher');
        return $this->user()->can('update', $voucher);
    }

    /**
     * اعتراض البيانات قبل الفحص لحل مشكلة الحسابات المساعدة وتحويلها إلى كلاسات كاملة عند التعديل
     */
    protected function prepareForValidation()
    {
        $typeMap = [
            'customer' => \App\Models\Customer::class,
            'client'   => \App\Models\Customer::class,
            'supplier' => \App\Models\Supplier::class,
            'treasury' => \App\Models\Treasury::class,
            'bank'     => \App\Models\Bank::class,
            'expense'  => \App\Models\Expense::class,
            'user'     => \App\Models\User::class,
            'designer' => \App\Models\User::class,
        ];

        $subLedgerType = $this->input('sub_ledger_type');

        // ترجمة النص المختصر إلى الاسم الكامل للكلاس إذا وجد في مصفوفة المطابقة
        if (array_key_exists($subLedgerType, $typeMap)) {
            $subLedgerType = $typeMap[$subLedgerType];
            $this->merge([
                'sub_ledger_type' => $subLedgerType,
            ]);
        }

        // إذا كان نوع الحساب المساعد مصمم أو موظف، نقوم بدمج معرف حسابه التجميعي آلياً
        if ($subLedgerType === \App\Models\User::class) {
            $account = Account::where('code', Account::CODE_DESIGNERS_LEDGER)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }

        // إذا كان نوع الحساب المساعد بنك، نقوم بدمج معرف حسابه التجميعي الرئيسي آلياً
        if ($subLedgerType === \App\Models\Bank::class) {
            $account = Account::where('code', Account::CODE_BANKS)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }

        // إذا كان نوع الحساب المساعد خزينة، نقوم بدمج معرف حسابه التجميعي الرئيسي آلياً
        if ($subLedgerType === \App\Models\Treasury::class) {
            $account = Account::where('code', Account::CODE_TREASURY)->first();

            $this->merge([
                'account_id' => $account ? $account->id : $this->input('account_id'),
            ]);
        }
    }

    /**
     * قواعد التحقق الصارمة لتعديل سندات الصرف والقبض
     */
    public function rules(): array
    {
        return [
            'voucher_type'    => ['required', Rule::in(['payment', 'receipt'])],
            'account_id'      => ['required', 'exists:accounts,id'],

            // التحقق من أن القيمة الممررة بعد الترجمة هي كلاس صريح ومعتمد للحسابات المساعدة
            'sub_ledger_type' => [
                'required',
                'string',
                Rule::in([
                    \App\Models\Customer::class,
                    \App\Models\Supplier::class,
                    \App\Models\Treasury::class,
                    \App\Models\Bank::class,
                    \App\Models\Expense::class,
                    \App\Models\User::class,
                ])
            ],
            'sub_ledger_id'   => ['required', 'integer'],

            'payment_method'  => ['required', Rule::in(['cash', 'bank'])],
            'fund_account_id' => ['required', 'exists:accounts,id'],

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
