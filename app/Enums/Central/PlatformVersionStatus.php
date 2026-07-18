<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PlatformVersionStatus: string
{
    case Draft = 'draft';
    case Released = 'released';
    case Deprecated = 'deprecated';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Released => 'Released',
            self::Deprecated => 'Deprecated',
            self::RolledBack => 'Rolled Back',
        };
    }
}
