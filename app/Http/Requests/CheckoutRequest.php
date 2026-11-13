<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,card,khata,split'],
            'tender_breakdown' => ['required', 'array'],
            'tender_breakdown.cash' => ['nullable', 'numeric', 'min:0'],
            'tender_breakdown.card' => ['nullable', 'numeric', 'min:0'],
            'tender_breakdown.khata' => ['nullable', 'numeric', 'min:0'],
            'tender_breakdown.cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'device_id' => ['nullable', 'string', 'max:30'],
            'cashier_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}

