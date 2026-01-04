<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Enums\HttpStatus;
use Exception;
use Illuminate\Http\JsonResponse;

final class ApiException extends Exception
{
    private HttpStatus $status;

    public function __construct(
        string $message,
        HttpStatus $status = HttpStatus::INTERNAL_SERVER_ERROR,
        private mixed $errors = null
    ) {
        parent::__construct($message, $status->value);
        $this->status = $status;
    }

    public function render(): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $this->message,
        ];

        if ($this->errors !== null) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->status->value);
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self($message, HttpStatus::NOT_FOUND);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self($message, HttpStatus::UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self($message, HttpStatus::FORBIDDEN);
    }

    public static function badRequest(string $message = 'Bad request', mixed $errors = null): self
    {
        return new self($message, HttpStatus::BAD_REQUEST, $errors);
    }

    public static function unprocessable(string $message = 'Unprocessable entity', mixed $errors = null): self
    {
        return new self($message, HttpStatus::UNPROCESSABLE_ENTITY, $errors);
    }
}
