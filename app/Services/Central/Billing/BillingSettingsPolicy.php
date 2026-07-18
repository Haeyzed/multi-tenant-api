<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\SubscriptionInterval;
use Illuminate\Validation\ValidationException;

final class BillingSettingsPolicy
{
    public const string FALLBACK_EXACT_CURRENCY = 'exact_currency';

    public const string FALLBACK_DEFAULT_CURRENCY = 'default_currency';

    public const string FALLBACK_SAME_INTERVAL = 'same_interval';

    public const string FALLBACK_ANY_ACTIVE = 'any_active';

    /**
     * @var list<string>
     */
    public const array PRICE_FALLBACK_MODES = [
        self::FALLBACK_EXACT_CURRENCY,
        self::FALLBACK_DEFAULT_CURRENCY,
        self::FALLBACK_SAME_INTERVAL,
        self::FALLBACK_ANY_ACTIVE,
    ];

    /**
     * @var array<string, array{min: int, max: int}>
     */
    public const array INTEGER_RULES = [
        'billing.invoice_due_days' => ['min' => 0, 'max' => 365],
        'billing.past_due_grace_days' => ['min' => 0, 'max' => 90],
        'billing.trial_reminder_days' => ['min' => 1, 'max' => 30],
        'billing.checkout_link_ttl_hours' => ['min' => 1, 'max' => 720],
        'billing.invoice_link_ttl_hours' => ['min' => 1, 'max' => 2160],
    ];

    /**
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    public function normalizeUpdates(array $updates, string $errorPrefix = 'settings.'): array
    {
        foreach (self::INTEGER_RULES as $key => $bounds) {
            if (!array_key_exists($key, $updates)) {
                continue;
            }

            $value = filter_var($updates[$key], FILTER_VALIDATE_INT);

            if ($value === false || $value < $bounds['min'] || $value > $bounds['max']) {
                $this->fail(
                    $key,
                    "The value must be an integer between {$bounds['min']} and {$bounds['max']}.",
                    $errorPrefix,
                );
            }

            $updates[$key] = $value;
        }

        if (array_key_exists('billing.default_interval', $updates)) {
            $interval = SubscriptionInterval::tryFrom(strtolower(trim((string)$updates['billing.default_interval'])));

            if ($interval === null || !$interval->isRecurring()) {
                $this->fail(
                    'billing.default_interval',
                    'The default interval must be monthly, quarterly, or yearly.',
                    $errorPrefix,
                );
            }

            $updates['billing.default_interval'] = $interval->value;
        }

        if (array_key_exists('billing.price_fallback_mode', $updates)) {
            $mode = strtolower(trim((string)$updates['billing.price_fallback_mode']));

            if (!in_array($mode, self::PRICE_FALLBACK_MODES, true)) {
                $this->fail(
                    'billing.price_fallback_mode',
                    'The price fallback mode is invalid.',
                    $errorPrefix,
                );
            }

            $updates['billing.price_fallback_mode'] = $mode;
        }

        if (array_key_exists('invoice.number_prefix', $updates)) {
            $prefix = trim((string)$updates['invoice.number_prefix']);

            if ($prefix === '' || strlen($prefix) > 12 || preg_match('/^[A-Za-z0-9_-]+$/', $prefix) !== 1) {
                $this->fail(
                    'invoice.number_prefix',
                    'The invoice prefix must be 1 to 12 letters, numbers, hyphens, or underscores.',
                    $errorPrefix,
                );
            }

            $updates['invoice.number_prefix'] = $prefix;
        }

        return $updates;
    }

    private function fail(string $key, string $message, string $errorPrefix): never
    {
        throw ValidationException::withMessages([
            $errorPrefix === 'value' ? 'value' : $errorPrefix . $key => [$message],
        ]);
    }
}
