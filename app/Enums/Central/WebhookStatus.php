<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum WebhookStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RETRYING = 'retrying';
    case DISABLED = 'disabled';
    case EXCEEDED_RETRIES = 'exceeded_retries';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $status): array => [
                ...$carry,
                $status->value => $status->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::RETRYING => 'Retrying',
            self::DISABLED => 'Disabled',
            self::EXCEEDED_RETRIES => 'Exceeded Retries',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::DELIVERED => 'green',
            self::FAILED => 'red',
            self::RETRYING => 'blue',
            self::DISABLED => 'gray',
            self::EXCEEDED_RETRIES => 'red',
        };
    }
}
