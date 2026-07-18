<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum DomainStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';
    case SUSPENDED = 'suspended';
    case VERIFICATION_PENDING = 'verification_pending';
    case VERIFICATION_FAILED = 'verification_failed';
    case SSL_PENDING = 'ssl_pending';
    case SSL_ACTIVE = 'ssl_active';
    case SSL_EXPIRED = 'ssl_expired';

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
            self::EXPIRED => 'Expired',
            self::SUSPENDED => 'Suspended',
            self::VERIFICATION_PENDING => 'Verification Pending',
            self::VERIFICATION_FAILED => 'Verification Failed',
            self::SSL_PENDING => 'SSL Pending',
            self::SSL_ACTIVE => 'SSL Active',
            self::SSL_EXPIRED => 'SSL Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::EXPIRED => 'red',
            self::SUSPENDED => 'red',
            self::VERIFICATION_PENDING => 'blue',
            self::VERIFICATION_FAILED => 'red',
            self::SSL_PENDING => 'orange',
            self::SSL_ACTIVE => 'green',
            self::SSL_EXPIRED => 'red',
        };
    }
}
