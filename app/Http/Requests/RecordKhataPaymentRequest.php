<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordKhataPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'narration' => ['nullable', 'string', 'max:255'],
            'receipt_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}

