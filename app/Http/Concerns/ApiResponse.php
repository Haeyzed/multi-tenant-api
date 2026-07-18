<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use stdClass;

/**
 * Standard JSON envelope helpers for API controllers.
 *
 * Every response uses: `{ status, message, data, meta, errors }`.
 * Scramble documents the payload under `data` via ApiResponseTypeInfer.
 */
trait ApiResponse
{
    /**
     * Return a successful JSON envelope.
     *
     * @template TData of array<string, mixed>|JsonResource|ResourceCollection|AbstractPaginator|null
     *
     * @param  TData  $data
     * @param  array<string, mixed>  $meta
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            return $this->paginated($data, $message, $status, $meta);
        }

        if ($data instanceof AbstractPaginator) {
            return $this->paginated($data, $message, $status, $meta);
        }

        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data ?? new stdClass,
            'meta' => (object) $meta,
            'errors' => null,
        ], $status);
    }

    /**
     * Return a paginated JSON envelope with pagination fields in `meta`.
     *
     * @param AbstractPaginator|ResourceCollection $paginator
     * @param string $message
     * @param int $status
     * @param array<string, mixed> $meta
     * @return JsonResponse
     */
    protected function paginated(
        AbstractPaginator|ResourceCollection $paginator,
        string $message = 'Success',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        if ($paginator instanceof ResourceCollection) {
            $resource = $paginator->resource;
            $items = $paginator->resolve(request());

            if (! $resource instanceof AbstractPaginator) {
                return $this->success($items, $message, $status, $meta);
            }

            $paginator = $resource;
            $data = $items;
        } else {
            $data = $paginator->items();
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) array_merge([
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'path' => $paginator->path(),
            ], $meta),
            'errors' => null,
        ], $status);
    }

    /**
     * Return a failed JSON envelope.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>  $meta
     */
    protected function error(
        string $message = 'An error occurred',
        int $status = 400,
        ?array $errors = null,
        mixed $data = null,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => $errors,
        ], $status);
    }
}
