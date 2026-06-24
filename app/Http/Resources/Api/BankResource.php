<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'account_number'  => $this->account_number,
            'iban'            => $this->iban,
            'is_active'       => (bool) $this->is_active,

            // [التعديل]: قراءة الرصيد الفعلي الخاص بهذا البنك فقط بناءً على حركاته المالية
            'current_balance' => $this->current_balance,

            'account_id'      => $this->account_id,
            'account'         => new AccountResource($this->whenLoaded('account')),

            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
