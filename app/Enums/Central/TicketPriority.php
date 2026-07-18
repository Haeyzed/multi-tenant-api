<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum TicketPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case CRITICAL = 'critical';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $priority): array => [
                ...$carry,
                $priority->value => $priority->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
            self::CRITICAL => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH => 'orange',
            self::URGENT => 'red',
            self::CRITICAL => 'red',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::LOW => 72,
            self::MEDIUM => 48,
            self::HIGH => 24,
            self::URGENT => 4,
            self::CRITICAL => 1,
        };
    }
}
