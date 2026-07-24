<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\Central\PaymentGateway;
use App\Models\World\Country;
use App\Models\World\Currency;
use App\Services\Central\Billing\PaymentGatewayConfigService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the platform payment gateway catalog and currency/country pivots.
 *
 * Mirrors the historical billing.gateway_by_currency / provider_currencies maps.
 */
class PaymentGatewaySeeder extends Seeder
{
    /**
     * @var array<string, array{
     *     name: string,
     *     driver: string,
     *     priority: int,
     *     is_fallback: bool,
     *     supports_subscription: bool,
     *     supports_refund: bool,
     *     supports_webhook: bool,
     *     supports_partial_refund: bool,
     *     currencies: list<string>,
     *     countries: list<array{iso2: string, priority: int}>
     * }>
     */
    private const GATEWAYS = [
        'paystack' => [
            'name' => 'Paystack',
            'driver' => 'paystack',
            'priority' => 10,
            'is_fallback' => true,
            'supports_subscription' => true,
            'supports_refund' => true,
            'supports_webhook' => true,
            'supports_partial_refund' => true,
            'currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
            'countries' => [
                ['iso2' => 'NG', 'priority' => 10],
                ['iso2' => 'GH', 'priority' => 10],
                ['iso2' => 'ZA', 'priority' => 10],
            ],
        ],
        'flutterwave' => [
            'name' => 'Flutterwave',
            'driver' => 'flutterwave',
            'priority' => 20,
            'is_fallback' => false,
            'supports_subscription' => false,
            'supports_refund' => true,
            'supports_webhook' => true,
            'supports_partial_refund' => true,
            'currencies' => ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'XAF', 'XOF', 'USD', 'EUR', 'GBP'],
            'countries' => [
                ['iso2' => 'KE', 'priority' => 10],
                ['iso2' => 'UG', 'priority' => 10],
                ['iso2' => 'TZ', 'priority' => 10],
                ['iso2' => 'NG', 'priority' => 20],
                ['iso2' => 'GH', 'priority' => 20],
                ['iso2' => 'ZA', 'priority' => 20],
            ],
        ],
        'stripe' => [
            'name' => 'Stripe',
            'driver' => 'stripe',
            'priority' => 30,
            'is_fallback' => false,
            'supports_subscription' => true,
            'supports_refund' => true,
            'supports_webhook' => true,
            'supports_partial_refund' => true,
            'currencies' => ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'XAF', 'XOF', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'countries' => [
                ['iso2' => 'US', 'priority' => 10],
                ['iso2' => 'GB', 'priority' => 10],
                ['iso2' => 'CA', 'priority' => 10],
                ['iso2' => 'AU', 'priority' => 10],
                ['iso2' => 'NG', 'priority' => 30],
            ],
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        foreach (self::GATEWAYS as $slug => $definition) {
            $gateway = PaymentGateway::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'driver' => $definition['driver'],
                    'priority' => $definition['priority'],
                    'is_active' => true,
                    'is_fallback' => $definition['is_fallback'],
                    'supports_subscription' => $definition['supports_subscription'],
                    'supports_refund' => $definition['supports_refund'],
                    'supports_webhook' => $definition['supports_webhook'],
                    'supports_partial_refund' => $definition['supports_partial_refund'],
                    'config' => null,
                ],
            );

            $this->syncCurrencies($gateway, $definition['currencies']);
            $this->syncCountries($gateway, $definition['countries']);
        }

        app(PaymentGatewayConfigService::class)->syncFromSettings();
    }

    /**
     * @param  list<string>  $codes
     */
    private function syncCurrencies(PaymentGateway $gateway, array $codes): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $sync = [];

        foreach ($codes as $index => $code) {
            $currency = Currency::query()
                ->where('code', strtoupper($code))
                ->orderBy('id')
                ->first();

            if ($currency === null) {
                continue;
            }

            $sync[$currency->id] = ['is_default' => $index === 0];
        }

        if ($sync !== []) {
            $gateway->currencies()->sync($sync);
        }
    }

    /**
     * @param  list<array{iso2: string, priority: int}>  $countries
     */
    private function syncCountries(PaymentGateway $gateway, array $countries): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $sync = [];

        foreach ($countries as $row) {
            $country = Country::query()->where('iso2', strtoupper($row['iso2']))->first();

            if ($country === null) {
                continue;
            }

            $sync[$country->id] = ['priority' => $row['priority']];
        }

        if ($sync !== []) {
            $gateway->countries()->sync($sync);
        }
    }
}
