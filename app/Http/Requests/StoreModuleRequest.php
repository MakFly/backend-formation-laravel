<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:modules,slug'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:video,text,interactive,quiz,assignment,mixed'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
