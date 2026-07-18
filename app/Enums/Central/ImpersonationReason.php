<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum ImpersonationReason: string
{
    case SUPPORT = 'support';
    case DEBUGGING = 'debugging';
    case AUDIT = 'audit';
    case ONBOARDING = 'onboarding';
    case EMERGENCY = 'emergency';
    case DEVELOPMENT = 'development';
    case BILLING_ISSUE = 'billing_issue';
    case SECURITY_REVIEW = 'security_review';
    case CUSTOM = 'custom';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $reason): array => [
                ...$carry,
                $reason->value => $reason->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPPORT => 'Customer Support',
            self::DEBUGGING => 'Technical Debugging',
            self::AUDIT => 'Security Audit',
            self::ONBOARDING => 'Onboarding Assistance',
            self::EMERGENCY => 'Emergency Response',
            self::DEVELOPMENT => 'Development Testing',
            self::BILLING_ISSUE => 'Billing Issue Resolution',
            self::SECURITY_REVIEW => 'Security Review',
            self::CUSTOM => 'Custom Reason',
        };
    }
}
