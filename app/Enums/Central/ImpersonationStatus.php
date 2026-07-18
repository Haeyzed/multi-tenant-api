<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum ImpersonationStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case LOGGED_OUT = 'logged_out';
    case REVOKED = 'revoked';
    case TIMED_OUT = 'timed_out';

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
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::LOGGED_OUT => 'Logged Out',
            self::REVOKED => 'Revoked',
            self::TIMED_OUT => 'Timed Out',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::EXPIRED => 'gray',
            self::LOGGED_OUT => 'blue',
            self::REVOKED => 'red',
            self::TIMED_OUT => 'orange',
        };
    }
}
