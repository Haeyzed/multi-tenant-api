<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $level): array => [
                ...$carry,
                $level->value => $level->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::DEBUG => 'Debug',
            self::INFO => 'Info',
            self::NOTICE => 'Notice',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
            self::CRITICAL => 'Critical',
            self::ALERT => 'Alert',
            self::EMERGENCY => 'Emergency',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEBUG => 'gray',
            self::INFO => 'blue',
            self::NOTICE => 'cyan',
            self::WARNING => 'yellow',
            self::ERROR => 'orange',
            self::CRITICAL => 'red',
            self::ALERT => 'red',
            self::EMERGENCY => 'red',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7,
        };
    }
}
