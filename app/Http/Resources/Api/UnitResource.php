<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
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
            'name'        => $this->name,
            'short_name'  => $this->short_name,
            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
