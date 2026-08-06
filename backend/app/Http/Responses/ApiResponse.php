<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Operasi berhasil', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => (object) $meta, 'errors' => null], $status);
    }

    protected function failure(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null, 'meta' => (object) [], 'errors' => $errors], $status);
    }
}
