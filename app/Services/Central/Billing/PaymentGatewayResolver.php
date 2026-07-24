<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway as PaymentGatewayEnum;
use App\Models\Central\PaymentGateway;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves which payment gateway to use for a tenant / country / currency.
 *
 * Database catalog is preferred when active gateways exist; otherwise falls
 * back to billing settings (dual-read until Module 8 cutover).
 */
final class PaymentGatewayResolver
{
    /**
     * Hosted checkout providers offered on the public invoice pay page.
     *
     * @var list<string>
     */
    private const array CUSTOMER_FACING = [
        PaymentGatewayEnum::PAYSTACK->value,
        PaymentGatewayEnum::FLUTTERWAVE->value,
        PaymentGatewayEnum::STRIPE->value,
    ];

    public function __construct(
        private readonly SettingService $settings,
        private readonly PaymentSettingsPolicy $paymentSettingsPolicy,
    ) {}

    /**
     * Available customer-facing gateways for a currency (configured ∩ currency support).
     *
     * Recommended gateway (from resolve) is sorted first and flagged.
     *
     * @return list<array{value: string, label: string, recommended: bool}>
     */
    public function optionsForCurrency(?string $currency, ?string $countryIso2 = null): array
    {
        $recommended = $this->resolve($currency, null, $countryIso2);
        $candidates = $this->customerFacingCandidates();
        $options = [];

        foreach ($candidates as $gateway) {
            if (! $this->isConfigured($gateway)) {
                continue;
            }

            if (! $this->supportsCurrency($gateway, $currency)) {
                continue;
            }

            $enum = PaymentGatewayEnum::tryFrom($gateway);

            $options[] = [
                'value' => $gateway,
                'label' => $enum?->label() ?? $gateway,
                'recommended' => $gateway === $recommended,
            ];
        }

        if ($options !== [] && ! collect($options)->contains('recommended', true)) {
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
     * Resolve a gateway driver slug.
     *
     * Order: explicit → preferred → country → currency → DB fallback → settings.
     */
    public function resolve(
        ?string $currency = null,
        ?string $explicitGateway = null,
        ?string $countryIso2 = null,
        ?string $preferredGateway = null,
    ): string {
        if (filled($explicitGateway)) {
            return Str::lower((string) $explicitGateway);
        }

        if ($this->catalogAvailable()) {
            $fromCatalog = $this->resolveFromCatalog($currency, $countryIso2, $preferredGateway);

            if ($fromCatalog !== null) {
                return $fromCatalog;
            }

            throw ValidationException::withMessages([
                'gateway' => ['No active payment gateway matches the requested preferred gateway, country, currency, or fallback configuration.'],
            ]);
        }

        return $this->resolveFromSettings($currency);
    }

    private function catalogAvailable(): bool
    {
        if (! Schema::hasTable('payment_gateways')) {
            return false;
        }

        return PaymentGateway::query()->active()->exists();
    }

    private function resolveFromCatalog(
        ?string $currency,
        ?string $countryIso2,
        ?string $preferredGateway,
    ): ?string {
        if (filled($preferredGateway)) {
            $slug = Str::lower((string) $preferredGateway);
            $exists = PaymentGateway::query()->active()->where('slug', $slug)->exists();

            if ($exists) {
                return $slug;
            }
        }

        if (filled($countryIso2)) {
            $byCountry = $this->resolveByCountry(Str::upper(trim($countryIso2)));

            if ($byCountry !== null) {
                return $byCountry;
            }
        }

        $currencyCode = Str::upper(trim((string) $currency));

        if ($currencyCode !== '') {
            $byCurrency = $this->resolveByCurrency($currencyCode);

            if ($byCurrency !== null) {
                return $byCurrency;
            }
        }

        $fallback = PaymentGateway::query()
            ->active()
            ->where('is_fallback', true)
            ->orderBy('priority')
            ->value('slug');

        return filled($fallback) ? Str::lower((string) $fallback) : null;
    }

    private function resolveByCountry(string $countryIso2): ?string
    {
        $slug = PaymentGateway::query()
            ->active()
            ->join('payment_gateway_countries', 'payment_gateways.id', '=', 'payment_gateway_countries.payment_gateway_id')
            ->join('countries', 'countries.id', '=', 'payment_gateway_countries.country_id')
            ->where('countries.iso2', $countryIso2)
            ->orderBy('payment_gateway_countries.priority')
            ->orderBy('payment_gateways.priority')
            ->value('payment_gateways.slug');

        return filled($slug) ? Str::lower((string) $slug) : null;
    }

    private function resolveByCurrency(string $currencyCode): ?string
    {
        $slug = PaymentGateway::query()
            ->active()
            ->whereHas('currencies', fn ($query) => $query->where('code', $currencyCode))
            ->orderBy('priority')
            ->value('slug');

        return filled($slug) ? Str::lower((string) $slug) : null;
    }

    private function resolveFromSettings(?string $currency): string
    {
        $currencyCode = Str::upper(trim((string) $currency));

        if ($currencyCode !== '') {
            $map = $this->currencyMap();

            if (isset($map[$currencyCode]) && filled($map[$currencyCode])) {
                return Str::lower((string) $map[$currencyCode]);
            }
        }

        $configured = $this->settings->get('billing.default_gateway');

        if (filled($configured)) {
            return Str::lower((string) $configured);
        }

        return Str::lower((string) config('payments.default', PaymentGatewayEnum::PAYSTACK->value));
    }

    /**
     * @return array<string, string>
     */
    private function currencyMap(): array
    {
        $map = $this->settings->get('billing.gateway_by_currency', []);

        if (! is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $currency => $gateway) {
            if (! is_string($currency) || ! is_string($gateway) || $gateway === '') {
                continue;
            }

            $normalized[Str::upper($currency)] = Str::lower($gateway);
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function customerFacingCandidates(): array
    {
        if ($this->catalogAvailable()) {
            return PaymentGateway::query()
                ->active()
                ->whereIn('slug', self::CUSTOMER_FACING)
                ->orderBy('priority')
                ->pluck('slug')
                ->map(fn ($slug): string => Str::lower((string) $slug))
                ->values()
                ->all();
        }

        return self::CUSTOMER_FACING;
    }

    /**
     * Whether a customer-facing gateway has credentials configured.
     */
    public function isConfigured(string $gateway): bool
    {
        $gateway = Str::lower($gateway);

        if (! in_array($gateway, self::CUSTOMER_FACING, true)) {
            return false;
        }

        return match ($gateway) {
            PaymentGatewayEnum::STRIPE->value => filled(config('payments.stripe.secret')),
            PaymentGatewayEnum::PAYSTACK->value => filled(config('payments.paystack.secret')),
            PaymentGatewayEnum::FLUTTERWAVE->value => filled(config('payments.flutterwave.secret')),
            default => false,
        };
    }

    /**
     * Whether the gateway accepts the given ISO 4217 currency.
     */
    public function supportsCurrency(string $gateway, ?string $currency): bool
    {
        $gateway = Str::lower($gateway);
        $currencyCode = Str::upper(trim((string) $currency));

        if ($currencyCode === '') {
            return false;
        }

        if ($this->catalogAvailable()) {
            $gatewayRow = PaymentGateway::query()->active()->where('slug', $gateway)->first();

            if ($gatewayRow !== null) {
                $inCatalog = $gatewayRow->currencies()->where('code', $currencyCode)->exists();

                return $inCatalog && $this->paymentSettingsPolicy->capabilitySupports($gateway, $currencyCode);
            }
        }

        if (! $this->paymentSettingsPolicy->capabilitySupports($gateway, $currencyCode)) {
            return false;
        }

        $configured = $this->settings->get('billing.provider_currencies');

        if (! is_array($configured)) {
            return true;
        }

        $enabled = $this->paymentSettingsPolicy->enabledProviderCurrencies($configured);

        return in_array($currencyCode, $enabled[$gateway] ?? [], true);
    }
}
