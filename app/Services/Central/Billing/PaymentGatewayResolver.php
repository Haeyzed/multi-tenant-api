<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Str;

/**
 * Resolves which payment gateway to use for a currency.
 *
 * Prefer an explicit gateway, then the billing.gateway_by_currency map,
 * then billing.default_gateway, then config('payments.default').
 */
final class PaymentGatewayResolver
{
    /**
     * Hosted checkout providers offered on the public invoice pay page.
     *
     * @var list<string>
     */
    private const array CUSTOMER_FACING = [
        PaymentGateway::PAYSTACK->value,
        PaymentGateway::FLUTTERWAVE->value,
        PaymentGateway::STRIPE->value,
    ];

    public function __construct(
        private readonly SettingService        $settings,
        private readonly PaymentSettingsPolicy $paymentSettingsPolicy,
    )
    {
    }

    /**
     * Available customer-facing gateways for a currency (configured ∩ currency support).
     *
     * Recommended gateway (from resolve / currency map) is sorted first and flagged.
     *
     * @return list<array{value: string, label: string, recommended: bool}>
     */
    public function optionsForCurrency(?string $currency): array
    {
        $recommended = $this->resolve($currency);
        $options = [];

        foreach (self::CUSTOMER_FACING as $gateway) {
            if (!$this->isConfigured($gateway)) {
                continue;
            }

            if (!$this->supportsCurrency($gateway, $currency)) {
                continue;
            }

            $enum = PaymentGateway::tryFrom($gateway);

            $options[] = [
                'value' => $gateway,
                'label' => $enum?->label() ?? $gateway,
                'recommended' => $gateway === $recommended,
            ];
        }

        if ($options !== [] && !collect($options)->contains('recommended', true)) {
            $options[0]['recommended'] = true;
        }

        usort($options, static function (array $a, array $b): int {
            if ($a['recommended'] === $b['recommended']) {
                return strcmp($a['label'], $b['label']);
            }

            return $a['recommended'] ? -1 : 1;
        });

        return $options;
    }

    /**
     * Resolve a gateway driver name for the given currency.
     */
    public function resolve(?string $currency = null, ?string $explicitGateway = null): string
    {
        if (filled($explicitGateway)) {
            return Str::lower((string)$explicitGateway);
        }

        $currencyCode = Str::upper(trim((string)$currency));

        if ($currencyCode !== '') {
            $map = $this->currencyMap();

            if (isset($map[$currencyCode]) && filled($map[$currencyCode])) {
                return Str::lower((string)$map[$currencyCode]);
            }
        }

        $configured = $this->settings->get('billing.default_gateway');

        if (filled($configured)) {
            return Str::lower((string)$configured);
        }

        return Str::lower((string)config('payments.default', PaymentGateway::PAYSTACK->value));
    }

    /**
     * @return array<string, string>
     */
    private function currencyMap(): array
    {
        $map = $this->settings->get('billing.gateway_by_currency', []);

        if (!is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $currency => $gateway) {
            if (!is_string($currency) || !is_string($gateway) || $gateway === '') {
                continue;
            }

            $normalized[Str::upper($currency)] = Str::lower($gateway);
        }

        return $normalized;
    }

    /**
     * Whether a customer-facing gateway has credentials configured.
     */
    public function isConfigured(string $gateway): bool
    {
        $gateway = Str::lower($gateway);

        if (!in_array($gateway, self::CUSTOMER_FACING, true)) {
            return false;
        }

        return match ($gateway) {
            PaymentGateway::STRIPE->value => filled(config('payments.stripe.secret')),
            PaymentGateway::PAYSTACK->value => filled(config('payments.paystack.secret')),
            PaymentGateway::FLUTTERWAVE->value => filled(config('payments.flutterwave.secret')),
            default => false,
        };
    }

    /**
     * Whether the gateway accepts the given ISO 4217 currency.
     */
    public function supportsCurrency(string $gateway, ?string $currency): bool
    {
        $gateway = Str::lower($gateway);
        $currencyCode = Str::upper(trim((string)$currency));

        if ($currencyCode === '') {
            return false;
        }

        if (!$this->paymentSettingsPolicy->capabilitySupports($gateway, $currencyCode)) {
            return false;
        }

        $configured = $this->settings->get('billing.provider_currencies');

        if (!is_array($configured)) {
            return true;
        }

        $enabled = $this->paymentSettingsPolicy->enabledProviderCurrencies($configured);

        return in_array($currencyCode, $enabled[$gateway] ?? [], true);
    }
}
