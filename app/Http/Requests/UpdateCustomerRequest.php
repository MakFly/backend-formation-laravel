<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:customers,email,'.$this->route('id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'in:individual,company'],
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'company_siret' => ['required_if:type,company', 'nullable', 'string', 'max:14'],
            'company_tva_number' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
