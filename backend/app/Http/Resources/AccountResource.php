<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'currency' => $this->currency,
            'initial_balance' => (int) $this->initial_balance,
            'current_balance' => (int) $this->current_balance,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'card_number' => $this->card_number,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'is_archived' => $this->is_archived,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
