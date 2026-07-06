<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function jsonSuccess(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    protected function jsonError(string $error, int $status = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => $data,
            'error' => $error,
        ], $status);
    }
}
