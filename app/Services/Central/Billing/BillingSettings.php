<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\SubscriptionInterval;
use App\Services\Central\Settings\SettingService;

final class BillingSettings
{
    public function __construct(
        private readonly SettingService $settings,
    )
    {
    }

    public function invoiceDueDays(): int
    {
        return $this->integer('billing.invoice_due_days', (int)config('billing.invoice_due_days', 7), 0, 365);
    }

    private function integer(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var($this->settings->get($key, $default), FILTER_VALIDATE_INT);

        if ($value === false) {
            return $default;
        }

        return max($minimum, min($maximum, $value));
    }

    public function pastDueGraceDays(): int
    {
        return $this->integer('billing.past_due_grace_days', (int)config('billing.past_due_grace_days', 3), 0, 90);
    }

    public function trialReminderDays(): int
    {
        return $this->integer('billing.trial_reminder_days', (int)config('billing.trial_reminder_days', 3), 1, 30);
    }

    public function checkoutLinkTtlHours(): int
    {
        return $this->integer('billing.checkout_link_ttl_hours', (int)config('billing.checkout_link_ttl_hours', 72), 1, 720);
    }

    public function invoiceLinkTtlHours(): int
    {
        return $this->integer('billing.invoice_link_ttl_hours', (int)config('billing.invoice_link_ttl_hours', 168), 1, 2160);
    }

    public function defaultInterval(): SubscriptionInterval
    {
        $fallback = SubscriptionInterval::tryFrom((string)config('billing.default_interval', 'monthly'))
            ?? SubscriptionInterval::MONTHLY;
        $value = SubscriptionInterval::tryFrom((string)$this->settings->get(
            'billing.default_interval',
            $fallback->value,
        ));

        return $value?->isRecurring() === true ? $value : $fallback;
    }

    public function priceFallbackMode(): string
    {
        $fallback = (string)config('billing.price_fallback_mode', BillingSettingsPolicy::FALLBACK_ANY_ACTIVE);
        $value = (string)$this->settings->get('billing.price_fallback_mode', $fallback);

        return in_array($value, BillingSettingsPolicy::PRICE_FALLBACK_MODES, true)
            ? $value
            : BillingSettingsPolicy::FALLBACK_ANY_ACTIVE;
    }

    public function invoiceNumberPrefix(): string
    {
        $fallback = (string)config('billing.invoice_number_prefix', 'INV-');
        $value = trim((string)$this->settings->get('invoice.number_prefix', $fallback));

        return $value !== '' ? $value : $fallback;
    }
}
