<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum EmailTemplateType: string
{
    case WELCOME = 'welcome';
    case PASSWORD_RESET = 'password_reset';
    case EMAIL_VERIFICATION = 'email_verification';
    case INVOICE = 'invoice';
    case PAYMENT_RECEIPT = 'payment_receipt';
    case PAYMENT_FAILED = 'payment_failed';
    case SUBSCRIPTION_CREATED = 'subscription_created';
    case SUBSCRIPTION_RENEWED = 'subscription_renewed';
    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    case SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    case SUBSCRIPTION_EXPIRED = 'subscription_expired';
    case TRIAL_ENDING = 'trial_ending';
    case TRIAL_ENDED = 'trial_ended';
    case TENANT_SUSPENDED = 'tenant_suspended';
    case TENANT_ACTIVATED = 'tenant_activated';
    case MAINTENANCE_NOTICE = 'maintenance_notice';
    case SECURITY_ALERT = 'security_alert';
    case ANNOUNCEMENT = 'announcement';
    case SUPPORT_TICKET_CREATED = 'support_ticket_created';
    case SUPPORT_TICKET_UPDATED = 'support_ticket_updated';
    case SUPPORT_TICKET_RESOLVED = 'support_ticket_resolved';
    case CUSTOM = 'custom';

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
            self::WELCOME => 'Welcome Email',
            self::PASSWORD_RESET => 'Password Reset',
            self::EMAIL_VERIFICATION => 'Email Verification',
            self::INVOICE => 'Invoice',
            self::PAYMENT_RECEIPT => 'Payment Receipt',
            self::PAYMENT_FAILED => 'Payment Failed',
            self::SUBSCRIPTION_CREATED => 'Subscription Created',
            self::SUBSCRIPTION_RENEWED => 'Subscription Renewed',
            self::SUBSCRIPTION_CANCELLED => 'Subscription Cancelled',
            self::SUBSCRIPTION_EXPIRING => 'Subscription Expiring',
            self::SUBSCRIPTION_EXPIRED => 'Subscription Expired',
            self::TRIAL_ENDING => 'Trial Ending',
            self::TRIAL_ENDED => 'Trial Ended',
            self::TENANT_SUSPENDED => 'Tenant Suspended',
            self::TENANT_ACTIVATED => 'Tenant Activated',
            self::MAINTENANCE_NOTICE => 'Maintenance Notice',
            self::SECURITY_ALERT => 'Security Alert',
            self::ANNOUNCEMENT => 'Announcement',
            self::SUPPORT_TICKET_CREATED => 'Support Ticket Created',
            self::SUPPORT_TICKET_UPDATED => 'Support Ticket Updated',
            self::SUPPORT_TICKET_RESOLVED => 'Support Ticket Resolved',
            self::CUSTOM => 'Custom Template',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::WELCOME, self::PASSWORD_RESET, self::EMAIL_VERIFICATION => 'auth',
            self::INVOICE, self::PAYMENT_RECEIPT, self::PAYMENT_FAILED => 'billing',
            self::SUBSCRIPTION_CREATED, self::SUBSCRIPTION_RENEWED, self::SUBSCRIPTION_CANCELLED,
            self::SUBSCRIPTION_EXPIRING, self::SUBSCRIPTION_EXPIRED, self::TRIAL_ENDING, self::TRIAL_ENDED => 'subscription',
            self::TENANT_SUSPENDED, self::TENANT_ACTIVATED => 'tenant',
            self::MAINTENANCE_NOTICE, self::SECURITY_ALERT, self::ANNOUNCEMENT => 'system',
            self::SUPPORT_TICKET_CREATED, self::SUPPORT_TICKET_UPDATED, self::SUPPORT_TICKET_RESOLVED => 'support',
            self::CUSTOM => 'custom',
        };
    }
}
