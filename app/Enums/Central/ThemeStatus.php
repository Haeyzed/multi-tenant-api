<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum ThemeStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case DEPRECATED = 'deprecated';
    case REMOVED = 'removed';

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
            self::PENDING_REVIEW => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PUBLISHED => 'Published',
            self::DEPRECATED => 'Deprecated',
            self::REMOVED => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_REVIEW => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::PUBLISHED => 'green',
            self::DEPRECATED => 'orange',
            self::REMOVED => 'red',
        };
    }
}
