<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum DomainType: string
{
    case SUBDOMAIN = 'subdomain';
    case CUSTOM = 'custom';
    case PRIMARY = 'primary';
    case REDIRECT = 'redirect';
    case ALIAS = 'alias';

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
            self::SUBDOMAIN => 'Subdomain',
            self::CUSTOM => 'Custom Domain',
            self::PRIMARY => 'Primary Domain',
            self::REDIRECT => 'Redirect',
            self::ALIAS => 'Alias',
        };
    }
}
