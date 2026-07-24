<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Models\Central\PaymentGateway;
use App\Models\Central\PaymentGatewayConfig;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Synchronizes legacy billing credentials into gateway environment records.
 */
final class PaymentGatewayConfigService
{
    /**
     * @var array<string, array{public: string, secret: string, webhook: string, config_public: string}>
     */
    private const PROVIDERS = [
        'stripe' => [
            'public' => 'billing.stripe_publishable',
            'secret' => 'billing.stripe_secret',
            'webhook' => 'billing.stripe_webhook_secret',
            'config_public' => 'payments.stripe.publishable',
        ],
        'paystack' => [
            'public' => 'billing.paystack_public',
            'secret' => 'billing.paystack_secret',
            'webhook' => 'billing.paystack_webhook_secret',
            'config_public' => 'payments.paystack.public',
        ],
        'flutterwave' => [
            'public' => 'billing.flutterwave_public',
            'secret' => 'billing.flutterwave_secret',
            'webhook' => 'billing.flutterwave_webhook_secret',
            'config_public' => 'payments.flutterwave.public',
        ],
    ];

    public function __construct(private readonly SettingService $settings) {}

    public function syncFromSettings(): void
    {
        if (! Schema::hasTable('payment_gateway_configs')) {
            return;
        }

        $environment = config('payments.mode', 'test') === 'live' ? 'live' : 'test';

        foreach (self::PROVIDERS as $slug => $keys) {
            $gateway = PaymentGateway::query()->where('slug', $slug)->first();

            if ($gateway === null) {
                continue;
            }

            $secret = $this->settings->get($keys['secret']);
            $public = $this->settings->get($keys['public']);
            $webhook = $this->settings->get($keys['webhook']);

            if (blank($secret) && blank($public) && blank($webhook)) {
                continue;
            }

            PaymentGatewayConfig::query()->updateOrCreate(
                [
                    'payment_gateway_id' => $gateway->id,
                    'environment' => $environment,
                ],
                [
                    'public_key' => filled($public) ? (string) $public : null,
                    'secret_key' => filled($secret) ? (string) $secret : null,
                    'webhook_secret' => filled($webhook) ? (string) $webhook : null,
                    'is_active' => true,
                ],
            );
        }
    }

    public function applyActiveEnvironmentToConfig(): void
    {
        if (! Schema::hasTable('payment_gateway_configs')) {
            return;
        }

        $environment = config('payments.mode', 'test') === 'live' ? 'live' : 'test';

        foreach (
            PaymentGatewayConfig::query()
                ->with('gateway:id,slug')
                ->where('environment', $environment)
                ->where('is_active', true)
                ->get() as $gatewayConfig
        ) {
            $slug = $gatewayConfig->gateway?->slug;

            if ($slug === null || ! isset(self::PROVIDERS[$slug])) {
                continue;
            }

            Config::set("payments.{$slug}.secret", $gatewayConfig->secret_key);
            Config::set("payments.{$slug}.webhook_secret", $gatewayConfig->webhook_secret);
            Config::set(self::PROVIDERS[$slug]['config_public'], $gatewayConfig->public_key);
        }
    }
}
