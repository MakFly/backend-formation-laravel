<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer,expired'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'refund amount',
            'reason' => 'refund reason',
        ];
    }
}
