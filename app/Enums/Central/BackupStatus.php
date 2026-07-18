<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum BackupStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PARTIAL = 'partial';
    case VERIFIED = 'verified';
    case RESTORING = 'restoring';
    case RESTORED = 'restored';

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
            self::RUNNING => 'Running',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::PARTIAL => 'Partial',
            self::VERIFIED => 'Verified',
            self::RESTORING => 'Restoring',
            self::RESTORED => 'Restored',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::RUNNING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::PARTIAL => 'orange',
            self::VERIFIED => 'green',
            self::RESTORING => 'purple',
            self::RESTORED => 'green',
        };
    }
}
