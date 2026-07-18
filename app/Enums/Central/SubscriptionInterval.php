<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionInterval: string
{
    case FREE = 'free';
    case TRIAL = 'trial';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
    case LIFETIME = 'lifetime';
    case ENTERPRISE = 'enterprise';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $interval): array => [
                ...$carry,
                $interval->value => $interval->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Free',
            self::TRIAL => 'Trial',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
            self::LIFETIME => 'Lifetime',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    /**
     * Get the number of days for the interval.
     */
    public function days(): int|null
    {
        return match ($this) {
            self::FREE => null,
            self::TRIAL => 14,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::YEARLY => 365,
            self::LIFETIME => null,
            self::ENTERPRISE => null,
        };
    }

    /**
     * Check if the interval is recurring.
     */
    public function isRecurring(): bool
    {
        return in_array($this, [self::MONTHLY, self::QUARTERLY, self::YEARLY], true);
    }

    /**
     * @return list<string>
     */
    public static function recurringValues(): array
    {
        return array_map(
            static fn (self $interval): string => $interval->value,
            array_filter(self::cases(), static fn (self $interval): bool => $interval->isRecurring()),
        );
    }

    /**
     * Check if the interval is time-limited.
     */
    public function isTimeLimited(): bool
    {
        return $this !== self::LIFETIME && $this !== self::ENTERPRISE && $this !== self::FREE;
    }
}
