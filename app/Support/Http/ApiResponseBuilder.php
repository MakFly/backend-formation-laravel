<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use App\Enums\HttpStatus;

final readonly class ApiResponseBuilder
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        HttpStatus|int $status = HttpStatus::OK
    ): JsonResponse {
        $statusCode = is_int($status) ? $status : $status->value;
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function created(
        mixed $data = null,
        ?string $message = 'Resource created successfully'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], HttpStatus::CREATED->value);
    }

    public static function error(
        string $message,
        HttpStatus $status = HttpStatus::INTERNAL_SERVER_ERROR,
        mixed $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status->value);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, HttpStatus::NO_CONTENT->value);
    }
}
