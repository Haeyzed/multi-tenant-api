<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum FeatureFlagType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case STRING = 'string';
    case JSON = 'json';
    case ARRAY = 'array';

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
            self::BOOLEAN => 'Boolean',
            self::INTEGER => 'Integer',
            self::STRING => 'String',
            self::JSON => 'JSON',
            self::ARRAY => 'Array',
        };
    }
}
