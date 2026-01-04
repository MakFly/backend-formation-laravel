<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:individual,company'],
            'company_name' => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'company_siret' => ['required_if:type,company', 'nullable', 'string', 'max:14'],
            'company_tva_number' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
