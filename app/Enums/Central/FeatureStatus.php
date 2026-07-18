<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum FeatureStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deprecated = 'deprecated';

    /**
     * @return array<string, string>
     */
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
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Deprecated => 'Deprecated',
        };
    }
}
