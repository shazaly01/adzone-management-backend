<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'credit_limit' => $this->credit_limit,
            'current_balance' => $this->current_balance,
            'account_id' => $this->account_id,

            // جلب تفاصيل الحساب المالي المرتبط من الشجرة في حال تم عمل Eager Loading له
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'code' => $this->account->code,
                    'type' => $this->account->type,
                    'opening_balance' => $this->account->opening_balance,
                    'current_balance' => $this->account->current_balance,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
