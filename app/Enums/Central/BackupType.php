<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum BackupType: string
{
    case FULL = 'full';
    case DATABASE = 'database';
    case FILES = 'files';
    case CONFIG = 'config';
    case INCREMENTAL = 'incremental';
    case DIFFERENTIAL = 'differential';

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
            self::FULL => 'Full Backup',
            self::DATABASE => 'Database Only',
            self::FILES => 'Files Only',
            self::CONFIG => 'Configuration Only',
            self::INCREMENTAL => 'Incremental',
            self::DIFFERENTIAL => 'Differential',
        };
    }
}
