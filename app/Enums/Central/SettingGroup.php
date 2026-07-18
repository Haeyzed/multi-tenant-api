<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SettingGroup: string
{
    case Platform = 'platform';
    case Mail = 'mail';
    case Storage = 'storage';
    case Localization = 'localization';
    case Security = 'security';
    case Maintenance = 'maintenance';
    case Api = 'api';
    case Media = 'media';
    case Cdn = 'cdn';
    case Ai = 'ai';
    case Oauth = 'oauth';
    case Captcha = 'captcha';
    case Backups = 'backups';
    case Billing = 'billing';
    case Invoice = 'invoice';
    case Tenant = 'tenant';

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $group): array => [
                ...$carry,
                $group->value => $group->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Mail => 'Mail',
            self::Storage => 'Storage',
            self::Localization => 'Localization',
            self::Security => 'Security',
            self::Maintenance => 'Maintenance',
            self::Api => 'API',
            self::Media => 'Media',
            self::Cdn => 'CDN',
            self::Ai => 'AI',
            self::Oauth => 'OAuth',
            self::Captcha => 'Captcha',
            self::Backups => 'Backups',
            self::Billing => 'Billing',
            self::Invoice => 'Invoice',
            self::Tenant => 'Tenant',
        };
    }
}
