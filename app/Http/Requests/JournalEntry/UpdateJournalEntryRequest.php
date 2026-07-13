<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;
use App\Models\Account;

class UpdateJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * اعتراض مصفوفة الأسطر وتطهيرها بتحويل النصوص المختصرة لكلاسات كاملة قبل الفحص عند التعديل
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
            'store'    => \App\Models\Store::class,
        ];

        if ($this->has('lines') && is_array($this->input('lines'))) {
            $lines = $this->input('lines');
            foreach ($lines as $index => $line) {
                if (isset($line['sub_ledger_type'])) {
                    $shortType = strtolower(trim($line['sub_ledger_type']));
                    if (array_key_exists($shortType, $typeMap)) {
                        $lines[$index]['sub_ledger_type'] = $typeMap[$shortType];
                    }
                }
            }
            $this->merge(['lines' => $lines]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // جلب معرف القيد الحالي من المسار لتجاهله في فحص فريد رقم القيد
        $journalEntryId = $this->route('journal_entry')?->id ?? $this->route('journal_entry');

        return [
            'entry_number'            => [
                'required',
                'string',
                'max:50',
                Rule::unique('journal_entries', 'entry_number')->ignore($journalEntryId)
            ],
            'entry_date'              => 'required|date',
            'type'                    => 'required|string|max:20',
            'notes'                   => 'nullable|string|max:1000',
            'lines'                   => 'required|array|min:2',
            'lines.*.account_id'      => 'required|integer|exists:accounts,id',
            'lines.*.debit'           => 'required|numeric|min:0',
            'lines.*.credit'          => 'required|numeric|min:0',
            'lines.*.line_notes'      => 'nullable|string|max:500',
            'lines.*.sub_ledger_type' => [
                'nullable',
                'string',
                Rule::in([
                    \App\Models\Customer::class,
                    \App\Models\Supplier::class,
                    \App\Models\Treasury::class,
                    \App\Models\Bank::class,
                    \App\Models\Expense::class,
                    \App\Models\User::class,
                    \App\Models\Store::class,
                ])
            ],
            'lines.*.sub_ledger_id'   => 'nullable|integer',
        ];
    }

    /**
     * قواعد تحقق مخصصة ومتطورة للعمليات المالية والقيود المركبة عند التعديل
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lines = $this->input('lines', []);
                $totalDebit = 0;
                $totalCredit = 0;

                // حماية الأداء: تجميع المعرفات وعمل استعلام موحد لمعرفة الحسابات الآباء
                $accountIds = array_filter(array_column($lines, 'account_id'));
                $parentAccountIds = Account::whereIn('parent_id', $accountIds)
                    ->pluck('parent_id')
                    ->unique()
                    ->toArray();

                foreach ($lines as $index => $line) {
                    $accountId = isset($line['account_id']) ? (int)$line['account_id'] : null;
                    $debit = isset($line['debit']) ? (float)$line['debit'] : 0.00;
                    $credit = isset($line['credit']) ? (float)$line['credit'] : 0.00;

                    // منع الحركة المادية على الحسابات الأب التجميعية
                    if ($accountId && in_array($accountId, $parentAccountIds)) {
                        $validator->errors()->add("lines.{$index}.account_id", "لا يمكن تسجيل حركات مالية مباشرة على حساب أب تجميعي. يرجى اختيار حساب فرعي نهائي.");
                    }

                    if ($debit == 0 && $credit == 0) {
                        $validator->errors()->add("lines.{$index}.debit", "يجب إدخال قيمة في خانة المدين أو الدائن، لا يمكن تركهما معاً بصفر.");
                    }

                    if ($debit > 0 && $credit > 0) {
                        $validator->errors()->add("lines.{$index}.credit", "لا يمكن إدخال قيمة في خانتي المدين والدائن معاً لنفس السطر.");
                    }

                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                    $validator->errors()->add('journal_balance', "القيد غير متوازن محاسبياً! إجمالي المدين ({$totalDebit}) يجب أن يساوي إجمالي الدائن ({$totalCredit}). الفرق الحالي: " . abs($totalDebit - $totalCredit));
                }
            }
        ];
    }

    /**
     * تخصيص أسماء الحقول لعرض أخطاء واضحة للمستخدم
     */
    public function attributes(): array
    {
        return [
            'entry_number'       => 'رقم القيد',
            'entry_date'         => 'تاريخ القيد',
            'type'               => 'نوع القيد',
            'lines'              => 'أسطر القيد',
            'lines.*.account_id' => 'الحساب المالي',
            'lines.*.debit'      => 'مدين',
            'lines.*.credit'     => 'دائن',
        ];
    }
}
