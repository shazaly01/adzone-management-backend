<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'location'   => $this->location,
            'is_active'  => (bool) $this->is_active,

            // جلب تفاصيل الحساب المالي المرتبط بالمخزن في شجرة الحسابات مباشرة
            'account_id' => $this->account_id,
            'account'    => new AccountResource($this->whenLoaded('account')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
