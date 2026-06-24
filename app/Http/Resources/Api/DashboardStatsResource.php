<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // بما أننا نمرر مصفوفة للـ Resource،
        // يمكننا الوصول إليها مباشرة عبر $this->resource
        return $this->resource;
    }
}
