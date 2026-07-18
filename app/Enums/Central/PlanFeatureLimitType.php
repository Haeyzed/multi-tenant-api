<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PlanFeatureLimitType: string
{
    case UNLIMITED = 'unlimited';
    case COUNT = 'count';
    case STORAGE = 'storage';
    case BANDWIDTH = 'bandwidth';
    case PERIODIC = 'periodic';
    case BOOLEAN = 'boolean';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $type): array => [
                ...$carry,
                $type->value => $type->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::UNLIMITED => 'Unlimited',
            self::COUNT => 'Count Limit',
            self::STORAGE => 'Storage Limit',
            self::BANDWIDTH => 'Bandwidth Limit',
            self::PERIODIC => 'Periodic Limit',
            self::BOOLEAN => 'Enabled/Disabled',
        };
    }
}
