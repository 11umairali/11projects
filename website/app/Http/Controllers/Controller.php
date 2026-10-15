<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * FIX-039: Return a safe error response that hides internals in production.
     */
    protected function safeErrorResponse(\Throwable $e, string $publicMessage = 'An error occurred.', int $status = 500): \Illuminate\Http\JsonResponse
    {
        \Illuminate\Support\Facades\Log::error($publicMessage, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $message = app()->environment('production')
            ? $publicMessage
            : "{$publicMessage}: {$e->getMessage()}";

        return response()->json(['message' => $message], $status);
    }
}
