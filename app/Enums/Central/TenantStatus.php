<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum TenantStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TRIAL = 'trial';
    case EXPIRED = 'expired';
    case GRACE_PERIOD = 'grace_period';
    case ARCHIVED = 'archived';

    /**
     * Get all values as an array.
     *
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

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::TRIAL => 'Trial',
            self::EXPIRED => 'Expired',
            self::GRACE_PERIOD => 'Grace Period',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Get the color associated with the status for UI rendering.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::SUSPENDED => 'red',
            self::TRIAL => 'blue',
            self::EXPIRED => 'gray',
            self::GRACE_PERIOD => 'orange',
            self::ARCHIVED => 'slate',
        };
    }

    /**
     * Get the icon name for the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::ACTIVE => 'check-circle',
            self::SUSPENDED => 'ban',
            self::TRIAL => 'flask',
            self::EXPIRED => 'x-circle',
            self::GRACE_PERIOD => 'alert-triangle',
            self::ARCHIVED => 'archive',
        };
    }

    /**
     * Check if the tenant can access the platform.
     */
    public function canAccess(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIAL, self::GRACE_PERIOD], true);
    }

    /**
     * Check if the tenant is in a billable state.
     */
    public function isBillable(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIAL, self::GRACE_PERIOD], true);
    }
}
