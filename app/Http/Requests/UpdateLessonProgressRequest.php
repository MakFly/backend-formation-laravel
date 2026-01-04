<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
            'current_position' => ['sometimes', 'integer', 'min:0'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'progress_percentage.required' => 'Progress percentage is required',
            'progress_percentage.integer' => 'Progress percentage must be an integer',
            'progress_percentage.between' => 'Progress percentage must be between 0 and 100',
            'current_position.integer' => 'Current position must be an integer',
            'current_position.min' => 'Current position cannot be negative',
            'time_spent_seconds.integer' => 'Time spent must be an integer',
            'time_spent_seconds.min' => 'Time spent cannot be negative',
        ];
    }
}
