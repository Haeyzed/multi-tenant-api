<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case TRIALING = 'trialing';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';
    case PAUSED = 'paused';
    case EXPIRED = 'expired';
    case UNPAID = 'unpaid';

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
            self::ACTIVE => 'Active',
            self::TRIALING => 'Trialing',
            self::PAST_DUE => 'Past Due',
            self::CANCELLED => 'Cancelled',
            self::PAUSED => 'Paused',
            self::EXPIRED => 'Expired',
            self::UNPAID => 'Unpaid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::TRIALING => 'blue',
            self::PAST_DUE => 'orange',
            self::CANCELLED => 'gray',
            self::PAUSED => 'purple',
            self::EXPIRED => 'red',
            self::UNPAID => 'red',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING, self::PAST_DUE], true);
    }
}
