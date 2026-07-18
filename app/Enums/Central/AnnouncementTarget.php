<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum AnnouncementTarget: string
{
    case ALL_TENANTS = 'all_tenants';
    case SPECIFIC_PLANS = 'specific_plans';
    case SPECIFIC_TENANTS = 'specific_tenants';
    case SPECIFIC_REGIONS = 'specific_regions';
    case ACTIVE_SUBSCRIBERS = 'active_subscribers';
    case TRIAL_USERS = 'trial_users';
    case ADMIN_ONLY = 'admin_only';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $target): array => [
                ...$carry,
                $target->value => $target->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::ALL_TENANTS => 'All Tenants',
            self::SPECIFIC_PLANS => 'Specific Plans',
            self::SPECIFIC_TENANTS => 'Specific Tenants',
            self::SPECIFIC_REGIONS => 'Specific Regions',
            self::ACTIVE_SUBSCRIBERS => 'Active Subscribers',
            self::TRIAL_USERS => 'Trial Users',
            self::ADMIN_ONLY => 'Admin Only',
        };
    }
}
