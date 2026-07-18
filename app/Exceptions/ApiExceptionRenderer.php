<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (!$request->is('api/*')) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'The given data was invalid.',
                'data' => null,
                'meta' => (object)[],
                'errors' => $e->errors(),
            ], $e->status);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'meta' => (object)[],
                'errors' => null,
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'data' => null,
                'meta' => (object)[],
                'errors' => null,
            ], 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'Resource not found.',
                'data' => null,
                'meta' => (object)[],
                'errors' => null,
            ], 404);
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $message = $e instanceof HttpExceptionInterface && $e->getMessage() !== ''
            ? $e->getMessage()
            : ($status === 500 ? 'Server Error' : $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => null,
            'meta' => (object)[],
            'errors' => null,
        ], $status);
    }
}
