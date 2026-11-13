<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'invoice_no' => $this->invoice_no,
            'fbr_invoice_no' => $this->fbr_invoice_no,
            'date' => $this->created_at->format('Y-m-d H:i:s'),
            'cashier_name' => $this->cashier_name,
            'device_id' => $this->device_id,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->product_name,
                        'unit' => $item->unit,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount_amount' => (float) $item->discount_amount,
                        'total' => (float) $item->total,
                    ];
                });
            }),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'grand_total' => (float) $this->grand_total,
            'payment_method' => $this->payment_method,
            'payments' => [
                'cash' => (float) $this->cash_amount,
                'card' => (float) $this->card_amount,
                'khata' => (float) $this->khata_amount,
            ],
            'cash_tendered' => $this->cash_tendered !== null ? (float) $this->cash_tendered : null,
            'change_given' => $this->change_given !== null ? (float) $this->change_given : null,
            'is_synced' => (bool) $this->is_synced,
        ];
    }
}

