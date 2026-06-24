<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'full_name'   => $this->full_name,
            'username'    => $this->username,
            'email'       => $this->email,
            'type'        => $this->type,
            'store_id'    => $this->store_id,
            'treasury_id' => $this->treasury_id,
            'bank_id'     => $this->bank_id,
            'created_at'  => $this->created_at->toDateTimeString(),

            // تحميل بيانات العلاقات كـ Objects عند طلبها لتفادي استعلامات N+1
            'store'       => $this->whenLoaded('store'),
            'treasury'    => $this->whenLoaded('treasury'),
            'bank'        => $this->whenLoaded('bank'),
            'roles'       => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
