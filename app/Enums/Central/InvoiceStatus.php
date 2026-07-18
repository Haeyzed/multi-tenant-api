<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case PAID = 'paid';
    case UNCOLLECTIBLE = 'uncollectible';
    case VOID = 'void';
    case PENDING = 'pending';
    case OVERDUE = 'overdue';

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
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::PAID => 'Paid',
            self::UNCOLLECTIBLE => 'Uncollectible',
            self::VOID => 'Void',
            self::PENDING => 'Pending',
            self::OVERDUE => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::OPEN => 'blue',
            self::PAID => 'green',
            self::UNCOLLECTIBLE => 'red',
            self::VOID => 'slate',
            self::PENDING => 'yellow',
            self::OVERDUE => 'red',
        };
    }
}
