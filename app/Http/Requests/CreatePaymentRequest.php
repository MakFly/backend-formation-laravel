<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'formation_id' => ['required', 'uuid', 'exists:formations,id'],
            'enrollment_id' => ['nullable', 'uuid', 'exists:enrollments,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'formation_id' => 'formation',
            'enrollment_id' => 'enrollment',
        ];
    }
}
