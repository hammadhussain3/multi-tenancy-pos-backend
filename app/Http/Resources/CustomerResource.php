<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentBalance = isset($this->total_debit) && isset($this->total_credit)
            ? (float) ($this->total_debit - $this->total_credit)
            : (float) $this->currentBalance;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'city' => $this->city,
            'credit_limit' => (float) $this->credit_limit,
            'current_balance' => $currentBalance,
            'notes' => $this->notes,
        ];
    }
}

