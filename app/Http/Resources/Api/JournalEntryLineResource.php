<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'journal_entry_id' => $this->journal_entry_id,
            'account_id'       => $this->account_id,
            'account_code'     => $this->whenLoaded('account', fn() => $this->account->code),
            'account_name'     => $this->whenLoaded('account', fn() => $this->account->name),
            'debit'            => (float) $this->debit,
            'credit'           => (float) $this->credit,
            'line_notes'       => $this->line_notes,
            'sub_ledger_type'  => $this->sub_ledger_type,
            'sub_ledger_id'    => $this->sub_ledger_id,
            'sub_ledger_name'  => $this->whenLoaded('subLedger', function() {
                return $this->subLedger ? $this->subLedger->name : null;
            }),
        ];
    }
}
