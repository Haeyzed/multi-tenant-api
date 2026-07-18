<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum IntegrationStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ERROR = 'error';
    case UPDATING = 'updating';
    case DEPRECATED = 'deprecated';
    case UNINSTALLED = 'uninstalled';

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
            self::INACTIVE => 'Inactive',
            self::ERROR => 'Error',
            self::UPDATING => 'Updating',
            self::DEPRECATED => 'Deprecated',
            self::UNINSTALLED => 'Uninstalled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::ERROR => 'red',
            self::UPDATING => 'blue',
            self::DEPRECATED => 'orange',
            self::UNINSTALLED => 'slate',
        };
    }
}
