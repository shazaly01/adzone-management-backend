<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockResource extends JsonResource
{
    /**
     * تحويل بيانات رأس المستند مع دمج سطور الأصناف المحملة
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'opening_number'   => $this->opening_number,
            'store_id'         => $this->store_id,
            'store_name'       => $this->store->name ?? null,
            'opening_date'     => $this->opening_date ? $this->opening_date->format('Y-m-d H:i:s') : null,
            'notes'            => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_no' => $this->journalEntry->entry_number ?? null,
            'user_id'          => $this->user_id,
            'user_name'        => $this->user->name ?? null,
            'created_at'       => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,

            // استدعاء مجمّع الأصناف بالـ Namespace الصحيح تماماً لحسم الخطأ
            'items'            => OpeningStockItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
