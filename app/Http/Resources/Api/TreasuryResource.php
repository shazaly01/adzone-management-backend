<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasuryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_active'  => (bool) $this->is_active,

            'current_balance' => $this->current_balance,
            // جلب تفاصيل الحساب المالي المرتبط بالشجرة مباشرة
            'account_id' => $this->account_id,
            'account'    => new AccountResource($this->whenLoaded('account')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
