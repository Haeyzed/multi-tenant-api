<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PaymentMethodStatus: string
{
    case Active = 'active';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Invalid => 'Invalid',
        };
    }
}
