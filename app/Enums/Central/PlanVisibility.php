<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PlanVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Hidden = 'hidden';

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $visibility): array => [
                ...$carry,
                $visibility->value => $visibility->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Hidden => 'Hidden',
        };
    }
}
