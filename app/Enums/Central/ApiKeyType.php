<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum ApiKeyType: string
{
    case PERSONAL = 'personal';
    case SERVICE = 'service';
    case WEBHOOK = 'webhook';
    case INTEGRATION = 'integration';
    case SYSTEM = 'system';

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
            self::PERSONAL => 'Personal Access Token',
            self::SERVICE => 'Service Account',
            self::WEBHOOK => 'Webhook',
            self::INTEGRATION => 'Integration',
            self::SYSTEM => 'System',
        };
    }
}
