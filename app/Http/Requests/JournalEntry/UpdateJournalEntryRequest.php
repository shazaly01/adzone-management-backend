<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
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
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $entryId = $this->route('journal_entry')?->id ?? $this->route('journal_entry');

        return [
            'entry_number'            => 'required|string|max:50|unique:journal_entries,entry_number,' . $entryId,
            'entry_date'              => 'required|date',
            'type'                    => 'required|string|max:20',
            'notes'                   => 'nullable|string|max:1000',
            'lines'                   => 'required|array|min:2',
            'lines.*.account_id'      => 'required|integer|exists:accounts,id',
            'lines.*.debit'           => 'required|numeric|min:0',
            'lines.*.credit'          => 'required|numeric|min:0',
            'lines.*.line_notes'      => 'nullable|string|max:500',
            'lines.*.sub_ledger_type' => 'nullable|string|max:255',
            'lines.*.sub_ledger_id'   => 'nullable|integer',
        ];
    }

    /**
     * قواعد تحقق مخصصة ومتطورة للعمليات المالية والقيود المركبة أثناء التعديل
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lines = $this->input('lines', []);
                $totalDebit = 0;
                $totalCredit = 0;

                // حماية الأداء: تجميع كافة المعرفات وعمل استعلام موحد لمعرفة الحسابات الآباء دفعة واحدة
                $accountIds = array_filter(array_column($lines, 'account_id'));
                $parentAccountIds = Account::whereIn('parent_id', $accountIds)
                    ->pluck('parent_id')
                    ->unique()
                    ->toArray();

                foreach ($lines as $index => $line) {
                    $accountId = isset($line['account_id']) ? (int)$line['account_id'] : null;
                    $debit = isset($line['debit']) ? (float)$line['debit'] : 0.00;
                    $credit = isset($line['credit']) ? (float)$line['credit'] : 0.00;

                    // الحارس المحاسبي: منع الحركة المادية على الحسابات الأب التجميعية
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
