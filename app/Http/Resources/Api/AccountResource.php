<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'type'            => $this->type,
            'opening_balance' => (float) $this->opening_balance,
            'current_balance' => (float) $this->current_balance,
            'parent_id'       => $this->parent_id,

            // شحن الحسابات الأبناء بشكل شجري تلقائي في حال تم استدعاؤهم بالـ Eager Loading
            'children'        => AccountResource::collection($this->whenLoaded('children')),

            // شحن الحساب الأب
            'parent'          => new AccountResource($this->whenLoaded('parent')),

            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
