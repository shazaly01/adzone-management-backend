<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendingInvoiceItemResource extends JsonResource
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
            'user_id'    => $this->user_id,
            'raw_text'   => $this->raw_text,
            'ai_output'  => $this->ai_output,
            'height'     => (float) $this->height,
            'width'      => (float) $this->width,
            'quantity'   => (int) $this->quantity,
            'price'      => (float) $this->price,
            'total'      => (float) (($this->height > 0 && $this->width > 0)
                            ? ($this->height * $this->width * $this->quantity * $this->price)
                            : ($this->quantity * $this->price)),
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,

            // تحميل بيانات الصنف المطابق تلقائياً في حال وجوده للمعاينة البشرية
            'item'       => $this->relationLoaded('item') && $this->item
                            ? [
                                'id'   => $this->item->id,
                                'name' => $this->item->name,
                              ]
                            : null,
        ];
    }
}
