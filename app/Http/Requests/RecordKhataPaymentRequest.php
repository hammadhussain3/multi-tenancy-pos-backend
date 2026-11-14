<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordKhataPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'receipt_no' => ['nullable', 'string', 'max:50'],
            'narration' => ['nullable', 'string', 'max:255'],
        ];
    }
}
