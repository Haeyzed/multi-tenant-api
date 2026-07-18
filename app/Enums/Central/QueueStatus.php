<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum QueueStatus: string
{
    case HEALTHY = 'healthy';
    case WARNING = 'warning';
    case CRITICAL = 'critical';
    case DOWN = 'down';
    case UNKNOWN = 'unknown';

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
            self::HEALTHY => 'Healthy',
            self::WARNING => 'Warning',
            self::CRITICAL => 'Critical',
            self::DOWN => 'Down',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HEALTHY => 'green',
            self::WARNING => 'yellow',
            self::CRITICAL => 'orange',
            self::DOWN => 'red',
            self::UNKNOWN => 'gray',
        };
    }
}
