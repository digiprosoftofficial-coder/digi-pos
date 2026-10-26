<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Standard JSON response envelope used across the API.
 */
trait ApiResponses
{
    protected function ok(mixed $data = null, string $message = '', array $meta = []): JsonResponse
    {
        return $this->success($data, $message, 200, $meta);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function success(mixed $data = null, string $message = '', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
