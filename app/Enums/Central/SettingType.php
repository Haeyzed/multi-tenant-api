<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SettingType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case JSON = 'json';
    case ARRAY = 'array';
    case FILE = 'file';
    case ENCRYPTED = 'encrypted';
    case COLOR = 'color';
    case URL = 'url';
    case EMAIL = 'email';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIMEZONE = 'timezone';
    case SELECT = 'select';
    case MULTI_SELECT = 'multi_select';
    case TEXTAREA = 'textarea';
    case RICH_TEXT = 'rich_text';
    case CODE = 'code';

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
            self::STRING => 'Text',
            self::INTEGER => 'Number (Integer)',
            self::FLOAT => 'Number (Decimal)',
            self::BOOLEAN => 'Toggle',
            self::JSON => 'JSON',
            self::ARRAY => 'Array',
            self::FILE => 'File Upload',
            self::ENCRYPTED => 'Encrypted',
            self::COLOR => 'Color Picker',
            self::URL => 'URL',
            self::EMAIL => 'Email',
            self::DATE => 'Date',
            self::DATETIME => 'Date & Time',
            self::TIMEZONE => 'Timezone',
            self::SELECT => 'Dropdown',
            self::MULTI_SELECT => 'Multi-Select',
            self::TEXTAREA => 'Text Area',
            self::RICH_TEXT => 'Rich Text Editor',
            self::CODE => 'Code Editor',
        };
    }

    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::STRING, self::URL, self::EMAIL, self::COLOR, self::TEXTAREA, self::RICH_TEXT, self::CODE => (string)$value,
            self::INTEGER => (int)$value,
            self::FLOAT => (float)$value,
            self::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::JSON => is_string($value) ? json_decode($value, true) : $value,
            self::ARRAY => is_string($value) ? json_decode($value, true) : (array)$value,
            self::FILE => $value,
            self::ENCRYPTED => $value,
            self::DATE => $value,
            self::DATETIME => $value,
            self::TIMEZONE => $value,
            self::SELECT => $value,
            self::MULTI_SELECT => is_string($value) ? json_decode($value, true) : (array)$value,
        };
    }
}
