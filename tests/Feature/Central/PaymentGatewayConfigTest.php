<?php

declare(strict_types=1);

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PaymentGatewayConfig;
use App\Models\Central\Setting;
use App\Services\Central\Billing\PaymentGatewayConfigService;
use App\Services\Central\Settings\ApplySettingsToConfig;
use App\Services\Central\Settings\SettingService;
use Database\Seeders\Central\PaymentGatewaySeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Cache::forget('central.settings.map');
});

it('syncs legacy settings into encrypted gateway config rows', function (): void {
    if (! Schema::hasTable('payment_gateway_configs')) {
        $this->markTestSkipped('payment_gateway_configs table is not available.');
    }

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    $this->seed(PaymentGatewaySeeder::class);

    Setting::query()->updateOrCreate(
        ['key' => 'billing.paystack_secret'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Paystack Secret Key',
            'type' => SettingType::ENCRYPTED,
            'value' => encrypt('sk_test_from_settings'),
            'default_value' => ['value' => null],
            'is_encrypted' => true,
        ],
    );
    Setting::query()->updateOrCreate(
        ['key' => 'billing.paystack_public'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Paystack Public Key',
            'type' => SettingType::STRING,
            'value' => 'pk_test_from_settings',
            'default_value' => ['value' => null],
        ],
    );
    app(SettingService::class)->forgetCache();

    app(PaymentGatewayConfigService::class)->syncFromSettings();

    $gateway = PaymentGateway::query()->where('slug', 'paystack')->firstOrFail();
    $config = PaymentGatewayConfig::query()
        ->where('payment_gateway_id', $gateway->id)
        ->where('environment', 'test')
        ->first();

    expect($config)->not->toBeNull()
        ->and($config?->secret_key)->toBe('sk_test_from_settings')
        ->and($config?->public_key)->toBe('pk_test_from_settings');
});

it('prefers active database configs when applying payments settings', function (): void {
    if (! Schema::hasTable('payment_gateway_configs')) {
        $this->markTestSkipped('payment_gateway_configs table is not available.');
    }

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    $this->seed(PaymentGatewaySeeder::class);

    $gateway = PaymentGateway::query()->where('slug', 'stripe')->firstOrFail();
    PaymentGatewayConfig::factory()->create([
        'payment_gateway_id' => $gateway->id,
        'environment' => 'test',
        'secret_key' => 'sk_db_override',
        'public_key' => 'pk_db_override',
        'webhook_secret' => 'whsec_db',
        'is_active' => true,
    ]);

    Setting::query()->updateOrCreate(
        ['key' => 'billing.mode'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Billing Mode',
            'type' => SettingType::SELECT,
            'value' => 'test',
            'default_value' => ['value' => 'test'],
            'options' => ['test', 'live'],
        ],
    );
    app(SettingService::class)->forgetCache();

    app(ApplySettingsToConfig::class)->apply();

    expect(config('payments.stripe.secret'))->toBe('sk_db_override')
        ->and(config('payments.stripe.publishable'))->toBe('pk_db_override')
        ->and(config('payments.stripe.webhook_secret'))->toBe('whsec_db');
});
