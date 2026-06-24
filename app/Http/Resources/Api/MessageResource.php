<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'content'      => $this->content,
            'phone'        => $this->phone,
            'type'         => $this->type, // individual, area, automated
            'status'       => $this->status, // pending, sent, failed
            'beneficiary'  => $this->beneficiary?->name ?? '---',
            'area'         => $this->area?->name ?? '---',
            'sender'       => $this->sender?->name ?? 'نظام آلي',
            'error_log'    => $this->error_log,
            'created_at'   => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
