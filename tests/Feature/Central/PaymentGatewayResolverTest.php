<?php

declare(strict_types=1);

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Models\Central\Setting;
use App\Services\Central\Billing\PaymentGatewayResolver;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('central.settings.map');

    Setting::query()->updateOrCreate(
        ['key' => 'billing.default_gateway'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Default Payment Gateway',
            'type' => SettingType::SELECT,
            'value' => 'paystack',
            'default_value' => ['value' => 'paystack'],
            'options' => ['paystack', 'flutterwave', 'stripe', 'manual'],
            'sort_order' => 1,
        ],
    );

    Setting::query()->updateOrCreate(
        ['key' => 'billing.gateway_by_currency'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Gateway By Currency',
            'type' => SettingType::JSON,
            'value' => json_encode([
                'NGN' => 'paystack',
                'USD' => 'stripe',
                'EUR' => 'stripe',
            ], JSON_THROW_ON_ERROR),
            'default_value' => ['value' => [
                'NGN' => 'paystack',
                'USD' => 'stripe',
                'EUR' => 'stripe',
            ]],
            'sort_order' => 2,
        ],
    );

    app(SettingService::class)->forgetCache();
});

it('resolves gateways from currency map then default setting', function (): void {
    $resolver = app(PaymentGatewayResolver::class);

    expect($resolver->resolve('NGN'))->toBe('paystack')
        ->and($resolver->resolve('USD'))->toBe('stripe')
        ->and($resolver->resolve('EUR'))->toBe('stripe')
        ->and($resolver->resolve('JPY'))->toBe('paystack')
        ->and($resolver->resolve('USD', 'flutterwave'))->toBe('flutterwave');
});

it('lists only configured gateways that support the invoice currency', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.stripe.secret' => 'sk_test',
        'payments.paystack.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
    ]);

    $resolver = app(PaymentGatewayResolver::class);
    $ngn = $resolver->optionsForCurrency('NGN');
    $values = collect($ngn)->pluck('value')->all();

    expect($values)->toContain('paystack', 'flutterwave', 'stripe')
        ->and(collect($ngn)->firstWhere('value', 'paystack')['recommended'])->toBeTrue();

    $kes = collect($resolver->optionsForCurrency('KES'))->pluck('value')->all();

    expect($kes)->toContain('flutterwave', 'stripe')
        ->and($kes)->not->toContain('paystack');
});

it('excludes unconfigured gateways in test mode', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.stripe.secret' => 'sk_test',
        'payments.paystack.secret' => null,
        'payments.flutterwave.secret' => null,
    ]);

    $resolver = app(PaymentGatewayResolver::class);
    $values = collect($resolver->optionsForCurrency('NGN'))->pluck('value')->all();

    expect($values)->toBe(['stripe']);
});

it('intersects configured provider currencies with server capabilities', function (): void {
    config([
        'payments.stripe.secret' => 'sk_test',
        'payments.paystack.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
    ]);

    Setting::query()->updateOrCreate(
        ['key' => 'billing.provider_currencies'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Enabled Provider Currencies',
            'type' => SettingType::JSON,
            'value' => json_encode([
                'paystack' => ['NGN'],
                'flutterwave' => ['KES'],
                'stripe' => ['USD'],
            ], JSON_THROW_ON_ERROR),
            'default_value' => ['value' => []],
        ],
    );
    app(SettingService::class)->forgetCache();

    $resolver = app(PaymentGatewayResolver::class);

    expect(collect($resolver->optionsForCurrency('NGN'))->pluck('value')->all())
        ->toBe(['paystack'])
        ->and(collect($resolver->optionsForCurrency('KES'))->pluck('value')->all())
        ->toBe(['flutterwave'])
        ->and(collect($resolver->optionsForCurrency('USD'))->pluck('value')->all())
        ->toBe(['stripe']);
});
