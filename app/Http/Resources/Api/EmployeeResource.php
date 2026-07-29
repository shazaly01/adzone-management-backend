<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * تحويل كائن الموظف إلى مصفوفة قابلة للعرض عبر API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'account_id'   => $this->account_id,
            'name'         => $this->name,
            'phone'        => $this->phone,
            'email'        => $this->email,
            'national_id'  => $this->national_id,
            'job_title'    => $this->job_title,
            'basic_salary' => (float) $this->basic_salary,
            'hire_date'    => $this->hire_date ? $this->hire_date->format('Y-m-d') : null,
            'is_active'    => (bool) $this->is_active,
            'notes'        => $this->notes,
            'account'      => $this->whenLoaded('account', function () {
                return [
                    'id'              => $this->account->id,
                    'code'            => $this->account->code,
                    'name'            => $this->account->name,
                    'type'            => $this->account->type,
                    'nature'          => $this->account->nature,
                    'current_balance' => (float) $this->account->current_balance,
                ];
            }),
            'created_at'   => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
