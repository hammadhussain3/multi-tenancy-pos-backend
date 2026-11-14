<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('tenant_id') && app()->bound('currentTenant')) {
            $this->merge([
                'tenant_id' => app('currentTenant')->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'string', 'in:purchase,return,adjustment,damage'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
