<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'formation_id' => ['required', 'uuid', 'exists:formations,id'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'payment_reference' => ['sometimes', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer ID is required',
            'customer_id.uuid' => 'Customer ID must be a valid UUID',
            'customer_id.exists' => 'Customer not found',
            'formation_id.required' => 'Formation ID is required',
            'formation_id.uuid' => 'Formation ID must be a valid UUID',
            'formation_id.exists' => 'Formation not found',
            'amount_paid.numeric' => 'Amount paid must be a number',
            'amount_paid.min' => 'Amount paid cannot be negative',
        ];
    }
}
