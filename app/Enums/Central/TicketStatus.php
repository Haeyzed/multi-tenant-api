<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum TicketStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case WAITING_ON_CUSTOMER = 'waiting_on_customer';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case ESCALATED = 'escalated';
    case ON_HOLD = 'on_hold';

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
            self::OPEN => 'Open',
            self::PENDING => 'Pending',
            self::WAITING_ON_CUSTOMER => 'Waiting on Customer',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::ESCALATED => 'Escalated',
            self::ON_HOLD => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'blue',
            self::PENDING => 'yellow',
            self::WAITING_ON_CUSTOMER => 'orange',
            self::RESOLVED => 'green',
            self::CLOSED => 'gray',
            self::ESCALATED => 'red',
            self::ON_HOLD => 'purple',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OPEN, self::PENDING, self::WAITING_ON_CUSTOMER, self::ESCALATED, self::ON_HOLD], true);
    }
}
