<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'parent_id'   => $this->parent_id,
            'parent_name' => $this->parent?->name, // جلب اسم الأب لسرعة العرض في الجداول
            'name'        => $this->name,
            'path'        => $this->path,
            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
