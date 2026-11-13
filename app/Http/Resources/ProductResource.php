<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'name_urdu' => $this->name_urdu,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'category' => $this->category,
            'unit' => $this->unit,
            'purchase_price' => (float) $this->purchase_price,
            'retail_price' => (float) $this->retail_price,
            'current_stock' => (int) ($this->current_stock ?? $this->currentStock),
            'min_stock_alert' => (int) $this->min_stock_alert,
            'is_active' => (bool) $this->is_active,
        ];
    }
}

