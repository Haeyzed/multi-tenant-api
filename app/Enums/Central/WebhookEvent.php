<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum WebhookEvent: string
{
    // Tenant Events
    case TENANT_CREATED = 'tenant.created';
    case TENANT_UPDATED = 'tenant.updated';
    case TENANT_DELETED = 'tenant.deleted';
    case TENANT_SUSPENDED = 'tenant.suspended';
    case TENANT_ACTIVATED = 'tenant.activated';

    // Subscription Events
    case SUBSCRIPTION_CREATED = 'subscription.created';
    case SUBSCRIPTION_UPDATED = 'subscription.updated';
    case SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    case SUBSCRIPTION_RENEWED = 'subscription.renewed';
    case SUBSCRIPTION_EXPIRED = 'subscription.expired';
    case SUBSCRIPTION_TRIAL_ENDED = 'subscription.trial_ended';

    // Payment Events
    case PAYMENT_SUCCEEDED = 'payment.succeeded';
    case PAYMENT_FAILED = 'payment.failed';
    case PAYMENT_REFUNDED = 'payment.refunded';
    case INVOICE_CREATED = 'invoice.created';
    case INVOICE_PAID = 'invoice.paid';
    case INVOICE_OVERDUE = 'invoice.overdue';

    // Domain Events
    case DOMAIN_CREATED = 'domain.created';
    case DOMAIN_VERIFIED = 'domain.verified';
    case DOMAIN_SSL_ISSUED = 'domain.ssl_issued';
    case DOMAIN_SSL_EXPIRED = 'domain.ssl_expired';

    // Feature Events
    case FEATURE_ENABLED = 'feature.enabled';
    case FEATURE_DISABLED = 'feature.disabled';
    case FEATURE_LIMIT_REACHED = 'feature.limit_reached';

    // System Events
    case BACKUP_COMPLETED = 'backup.completed';
    case BACKUP_FAILED = 'backup.failed';
    case MAINTENANCE_STARTED = 'maintenance.started';
    case MAINTENANCE_ENDED = 'maintenance.ended';
    case ANNOUNCEMENT_PUBLISHED = 'announcement.published';

    public static function byCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            static fn(self $event): bool => $event->category() === $category
        );
    }

    public function category(): string
    {
        return explode('.', $this->value)[0];
    }

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $event): array => [
                ...$carry,
                $event->value => $event->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return str_replace('_', ' ', str_replace('.', ' — ', $this->value));
    }
}
