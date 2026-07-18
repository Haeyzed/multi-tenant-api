<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum AnnouncementType: string
{
    case MAINTENANCE = 'maintenance';
    case SECURITY = 'security';
    case FEATURE_RELEASE = 'feature_release';
    case BUG_FIX = 'bug_fix';
    case SYSTEM_NOTICE = 'system_notice';
    case PLATFORM_NEWS = 'platform_news';
    case DEPRECATION = 'deprecation';
    case EMERGENCY = 'emergency';

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
            self::MAINTENANCE => 'Maintenance',
            self::SECURITY => 'Security Update',
            self::FEATURE_RELEASE => 'Feature Release',
            self::BUG_FIX => 'Bug Fix',
            self::SYSTEM_NOTICE => 'System Notice',
            self::PLATFORM_NEWS => 'Platform News',
            self::DEPRECATION => 'Deprecation Notice',
            self::EMERGENCY => 'Emergency',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MAINTENANCE => 'blue',
            self::SECURITY => 'red',
            self::FEATURE_RELEASE => 'green',
            self::BUG_FIX => 'purple',
            self::SYSTEM_NOTICE => 'gray',
            self::PLATFORM_NEWS => 'indigo',
            self::DEPRECATION => 'orange',
            self::EMERGENCY => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MAINTENANCE => 'wrench',
            self::SECURITY => 'shield',
            self::FEATURE_RELEASE => 'sparkles',
            self::BUG_FIX => 'bug',
            self::SYSTEM_NOTICE => 'info',
            self::PLATFORM_NEWS => 'newspaper',
            self::DEPRECATION => 'alert-triangle',
            self::EMERGENCY => 'alert-octagon',
        };
    }
}
