<?php

declare(strict_types=1);

namespace App\Scramble\Extensions;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use stdClass;

/**
 * Helps Scramble document `$this->success()` / `paginated()` / `error()` envelopes
 * with the real payload type under the `data` key.
 */
final class ApiResponseTypeInfer implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Controller::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'success' => $this->successResponse($event),
            'paginated' => $this->paginatedResponse($event),
            'error' => $this->errorResponse($event),
            default => null,
        };
    }

    private function successResponse(MethodCallEvent $event): Generic
    {
        $data = $event->getArg('data', 0, new NullType);
        $message = $event->getArg('message', 1, new LiteralStringType('Success'));
        $status = $event->getArg('status', 2, new LiteralIntegerType(200));
        $meta = $event->getArg('meta', 3, new KeyedArrayType);

        if ($this->isPaginatedPayload($data)) {
            return $this->envelope(
                data: $this->collectionDataType($data),
                message: $message,
                status: $status,
                meta: $this->paginationMetaType($meta),
                successful: true,
            );
        }

        return $this->envelope(
            data: $this->normalizeSuccessData($data),
            message: $message,
            status: $status,
            meta: $this->normalizeMeta($meta),
            successful: true,
        );
    }

    private function paginatedResponse(MethodCallEvent $event): Generic
    {
        $paginator = $event->getArg('paginator', 0, new UnknownType);
        $message = $event->getArg('message', 1, new LiteralStringType('Success'));
        $status = $event->getArg('status', 2, new LiteralIntegerType(200));
        $meta = $event->getArg('meta', 3, new KeyedArrayType);

        return $this->envelope(
            data: $this->collectionDataType($paginator),
            message: $message,
            status: $status,
            meta: $this->paginationMetaType($meta),
            successful: true,
        );
    }

    private function errorResponse(MethodCallEvent $event): Generic
    {
        $message = $event->getArg('message', 0, new LiteralStringType('An error occurred'));
        $status = $event->getArg('status', 1, new LiteralIntegerType(400));
        $errors = $event->getArg('errors', 2, new NullType);
        $data = $event->getArg('data', 3, new NullType);
        $meta = $event->getArg('meta', 4, new KeyedArrayType);

        return $this->envelope(
            data: $data instanceof NullType ? new NullType : $this->normalizeSuccessData($data),
            message: $message,
            status: $status,
            meta: $this->normalizeMeta($meta),
            successful: false,
            errors: $errors instanceof NullType
                ? new NullType
                : $errors,
        );
    }

    private function envelope(
        Type $data,
        Type $message,
        Type $status,
        Type $meta,
        bool $successful,
        ?Type $errors = null,
    ): Generic {
        return new Generic(JsonResponse::class, [
            new KeyedArrayType([
                new ArrayItemType_('status', new LiteralBooleanType($successful)),
                new ArrayItemType_('message', $message),
                new ArrayItemType_('data', $data),
                new ArrayItemType_('meta', $meta),
                new ArrayItemType_('errors', $errors ?? new NullType),
            ]),
            $status,
        ]);
    }

    private function normalizeSuccessData(Type $data): Type
    {
        if ($data instanceof NullType || $data instanceof UnknownType) {
            return new ObjectType(stdClass::class);
        }

        if ($data instanceof ObjectType && $data->isInstanceOf(ResourceCollection::class)) {
            return $this->collectionDataType($data);
        }

        return $data;
    }

    private function normalizeMeta(Type $meta): Type
    {
        if ($meta instanceof KeyedArrayType) {
            // Empty list arrays render as `[]` in OpenAPI; runtime casts meta to `{}`.
            if ($meta->items === []) {
                return new ObjectType(stdClass::class);
            }

            return $meta;
        }

        if ($meta instanceof UnknownType || $meta instanceof NullType) {
            return new ObjectType(stdClass::class);
        }

        return $meta;
    }

    private function paginationMetaType(Type $extraMeta): Type
    {
        $items = [
            new ArrayItemType_('current_page', new IntegerType),
            new ArrayItemType_('from', new Union([new IntegerType, new NullType])),
            new ArrayItemType_('last_page', new IntegerType),
            new ArrayItemType_('per_page', new IntegerType),
            new ArrayItemType_('to', new Union([new IntegerType, new NullType])),
            new ArrayItemType_('total', new IntegerType),
            new ArrayItemType_('path', new StringType),
        ];

        if ($extraMeta instanceof KeyedArrayType) {
            foreach ($extraMeta->items as $item) {
                $items[] = $item;
            }
        }

        return new KeyedArrayType($items);
    }

    private function collectionDataType(Type $type): Type
    {
        if ($type instanceof ObjectType && $type->isInstanceOf(ResourceCollection::class)) {
            $collected = ResourceCollectionTypeManager::make($type)->getCollectedType();

            if ($collected instanceof ObjectType) {
                return new ArrayType(value: $collected);
            }
        }

        if ($type instanceof ObjectType && (
            $type->isInstanceOf(AbstractPaginator::class)
            || $type->isInstanceOf(AbstractCursorPaginator::class)
        )) {
            $value = $type instanceof Generic
                ? ($type->templateTypes[0] ?? new UnknownType)
                : new UnknownType;

            return new ArrayType(value: $value);
        }

        return new ArrayType;
    }

    private function isPaginatedPayload(Type $data): bool
    {
        if ($data instanceof ObjectType && (
            $data->isInstanceOf(AbstractPaginator::class)
            || $data->isInstanceOf(AbstractCursorPaginator::class)
        )) {
            return true;
        }

        if (! ($data instanceof ObjectType) || ! $data->isInstanceOf(ResourceCollection::class)) {
            return false;
        }

        $resourceType = $data instanceof Generic
            ? ($data->templateTypes[0] ?? null)
            : null;

        return $resourceType instanceof ObjectType && (
            $resourceType->isInstanceOf(AbstractPaginator::class)
            || $resourceType->isInstanceOf(AbstractCursorPaginator::class)
        );
    }
}
