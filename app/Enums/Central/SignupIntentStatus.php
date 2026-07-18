<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SignupIntentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Completed = 'completed';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
        };
    }
}
