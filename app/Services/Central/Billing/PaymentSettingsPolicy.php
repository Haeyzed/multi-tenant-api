<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Normalizes and validates cross-setting payment currency policy.
 */
final class PaymentSettingsPolicy
{
    /** @var list<string> */
    public const GATEWAYS = ['paystack', 'flutterwave', 'stripe'];

    /** @var list<string> */
    private const POLICY_KEYS = [
        'billing.provider_currencies',
        'billing.gateway_by_currency',
        'billing.card_verification_amounts',
        'billing.card_verification_minimums',
    ];

    /**
     * @param array<string, mixed> $updates
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalizeUpdates(array $updates, array $current): array
    {
        if (array_intersect(array_keys($updates), self::POLICY_KEYS) === []) {
            return $updates;
        }

        $state = array_replace($current, $updates);
        $providerCurrencies = $this->normalizeProviderCurrencies(
            $state['billing.provider_currencies'] ?? [],
        );
        $amounts = $this->normalizeCurrencyAmounts(
            $state['billing.card_verification_amounts'] ?? [],
            'billing.card_verification_amounts',
        );
        $minimums = $this->normalizeMinimums(
            $state['billing.card_verification_minimums'] ?? [],
        );
        $gatewayMap = $this->normalizeGatewayMap(
            $state['billing.gateway_by_currency'] ?? [],
            $providerCurrencies,
        );

        $this->validateVerificationAmounts($providerCurrencies, $amounts, $minimums);

        $normalized = [
            'billing.provider_currencies' => $providerCurrencies,
            'billing.gateway_by_currency' => $gatewayMap,
            'billing.card_verification_amounts' => $amounts,
            'billing.card_verification_minimums' => $minimums,
        ];

        foreach ($normalized as $key => $value) {
            if (array_key_exists($key, $updates)) {
                $updates[$key] = $value;
            }
        }

        return $updates;
    }

    /**
     * @return array<string, list<string>>
     *
     * @throws ValidationException
     */
    private function normalizeProviderCurrencies(mixed $value): array
    {
        if (!is_array($value)) {
            $this->fail('billing.provider_currencies', 'Provider currencies must be an object keyed by provider.');
        }

        $unknown = array_diff(array_keys($value), self::GATEWAYS);

        if ($unknown !== []) {
            $this->fail('billing.provider_currencies', 'Only Paystack, Flutterwave, and Stripe may be configured.');
        }

        $normalized = [];

        foreach (self::GATEWAYS as $gateway) {
            $currencies = $value[$gateway] ?? [];

            if (!is_array($currencies) || !array_is_list($currencies)) {
                $this->fail(
                    "billing.provider_currencies.{$gateway}",
                    'Provider currencies must be a list of ISO currency codes.',
                );
            }

            $normalized[$gateway] = [];

            foreach ($currencies as $currency) {
                $code = $this->validatedCurrencyCode(
                    $currency,
                    "billing.provider_currencies.{$gateway}",
                );

                if (!$this->capabilitySupports($gateway, $code)) {
                    $this->fail(
                        "billing.provider_currencies.{$gateway}",
                        "{$gateway} does not support {$code}.",
                    );
                }

                $normalized[$gateway][] = $code;
            }

            $normalized[$gateway] = array_values(array_unique($normalized[$gateway]));
            sort($normalized[$gateway]);
        }

        return $normalized;
    }

    /**
     * @return never
     *
     * @throws ValidationException
     */
    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([
            "settings.{$key}" => [$message],
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function validatedCurrencyCode(mixed $value, string $key): string
    {
        $code = $this->currencyCode($value);

        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            $this->fail($key, 'Use a valid three-letter ISO currency code.');
        }

        return $code;
    }

    private function currencyCode(mixed $value): string
    {
        return Str::upper(trim((string)$value));
    }

    public function capabilitySupports(string $gateway, string $currency): bool
    {
        $gateway = Str::lower(trim($gateway));
        $currency = $this->currencyCode($currency);

        /** @var array<string, list<string>> $capabilities */
        $capabilities = config('payments.provider_currencies', []);
        $supported = $capabilities[$gateway] ?? [];

        return in_array('*', $supported, true)
            || in_array($currency, array_map(
                static fn(string $code): string => Str::upper($code),
                $supported,
            ), true);
    }

    /**
     * @return array<string, float>
     *
     * @throws ValidationException
     */
    private function normalizeCurrencyAmounts(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            $this->fail($key, 'Verification amounts must be an object keyed by currency.');
        }

        $normalized = [];

        foreach ($value as $currency => $amount) {
            $code = $this->validatedCurrencyCode($currency, $key);
            $normalized[$code] = $this->validatedAmount($amount, "{$key}.{$code}");
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @throws ValidationException
     */
    private function validatedAmount(mixed $value, string $key): float
    {
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
            $this->fail($key, 'Enter a finite amount greater than or equal to zero.');
        }

        return (float)$value;
    }

    /**
     * @return array<string, array<string, float>>
     *
     * @throws ValidationException
     */
    private function normalizeMinimums(mixed $value): array
    {
        if (!is_array($value)) {
            $this->fail('billing.card_verification_minimums', 'Verification minimums must be keyed by provider.');
        }

        $unknown = array_diff(array_keys($value), self::GATEWAYS);

        if ($unknown !== []) {
            $this->fail('billing.card_verification_minimums', 'Minimums contain an unsupported provider.');
        }

        $normalized = [];

        foreach (self::GATEWAYS as $gateway) {
            $amounts = $value[$gateway] ?? [];

            if (!is_array($amounts)) {
                $this->fail(
                    "billing.card_verification_minimums.{$gateway}",
                    'Provider minimums must be an object keyed by currency.',
                );
            }

            $normalized[$gateway] = $this->normalizeCurrencyAmounts(
                $amounts,
                "billing.card_verification_minimums.{$gateway}",
            );

            foreach (array_keys($normalized[$gateway]) as $currency) {
                if (!$this->capabilitySupports($gateway, $currency)) {
                    $this->fail(
                        "billing.card_verification_minimums.{$gateway}.{$currency}",
                        "{$gateway} does not support {$currency}.",
                    );
                }
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, list<string>> $providerCurrencies
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function normalizeGatewayMap(mixed $value, array $providerCurrencies): array
    {
        if (!is_array($value)) {
            $this->fail('billing.gateway_by_currency', 'Gateway routing must be an object keyed by currency.');
        }

        $normalized = [];

        foreach ($value as $currency => $gateway) {
            $code = $this->validatedCurrencyCode($currency, 'billing.gateway_by_currency');
            $gateway = Str::lower(trim((string)$gateway));

            if (!in_array($gateway, self::GATEWAYS, true)) {
                $this->fail("billing.gateway_by_currency.{$code}", 'Select a supported payment provider.');
            }

            if (!in_array($code, $providerCurrencies[$gateway] ?? [], true)) {
                $this->fail(
                    "billing.gateway_by_currency.{$code}",
                    "{$gateway} is not enabled for {$code}.",
                );
            }

            $normalized[$code] = $gateway;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, list<string>> $providerCurrencies
     * @param array<string, float> $amounts
     * @param array<string, array<string, float>> $minimums
     *
     * @throws ValidationException
     */
    private function validateVerificationAmounts(
        array $providerCurrencies,
        array $amounts,
        array $minimums,
    ): void
    {
        foreach ($providerCurrencies as $gateway => $currencies) {
            if ($gateway === 'stripe') {
                continue;
            }

            foreach ($currencies as $currency) {
                if (!array_key_exists($currency, $amounts)) {
                    $this->fail(
                        "billing.card_verification_amounts.{$currency}",
                        "Add a verification amount for {$currency}.",
                    );
                }

                $minimum = $minimums[$gateway][$currency] ?? 0.01;

                if ($amounts[$currency] < $minimum) {
                    $this->fail(
                        "billing.card_verification_amounts.{$currency}",
                        "The amount must be at least {$minimum} for {$gateway}.",
                    );
                }
            }
        }
    }

    /**
     * @param mixed $configured
     * @return array<string, list<string>>
     */
    public function enabledProviderCurrencies(mixed $configured): array
    {
        if (!is_array($configured)) {
            return [];
        }

        $enabled = [];

        foreach (self::GATEWAYS as $gateway) {
            $currencies = $configured[$gateway] ?? [];

            if (!is_array($currencies)) {
                $enabled[$gateway] = [];

                continue;
            }

            $enabled[$gateway] = array_values(array_unique(array_filter(array_map(
                fn(mixed $currency): string => $this->currencyCode($currency),
                $currencies,
            ))));
        }

        return $enabled;
    }

    /**
     * @param mixed $configured
     */
    public function verificationAmount(mixed $configured, string $currency, float $fallback): float
    {
        if (!is_array($configured)) {
            return max(0.0, $fallback);
        }

        $currency = $this->currencyCode($currency);
        $amount = $configured[$currency] ?? $fallback;

        return is_numeric($amount) && is_finite((float)$amount)
            ? max(0.0, (float)$amount)
            : max(0.0, $fallback);
    }

    /**
     * @param mixed $configured
     */
    public function verificationMinimum(mixed $configured, string $gateway, string $currency): float
    {
        if ($gateway === 'stripe') {
            return 0.0;
        }

        $minimums = is_array($configured) ? ($configured[$gateway] ?? []) : [];
        $minimum = is_array($minimums) ? ($minimums[$this->currencyCode($currency)] ?? 0.01) : 0.01;

        return is_numeric($minimum) && is_finite((float)$minimum)
            ? max(0.0, (float)$minimum)
            : 0.01;
    }
}
