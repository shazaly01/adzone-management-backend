<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date'   => $this->entry_date ? $this->entry_date->format('Y-m-d') : null,
            'type'         => $this->type,
            'notes'        => $this->notes,
            'user_id'      => $this->user_id,
            'user_name'    => $this->whenLoaded('user', fn() => $this->user->name),
            'lines'        => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at'   => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->toDateTimeString() : null,

            // حساب الإجماليات بشكل لحظي للمستند عند طلبه بالكامل لتسهيل العرض في الـ Frontend
            'total_debit'  => $this->whenLoaded('lines', fn() => (float) $this->lines->sum('debit')),
            'total_credit' => $this->whenLoaded('lines', fn() => (float) $this->lines->sum('credit')),
        ];
    }
}
