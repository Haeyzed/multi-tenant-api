<?php

declare(strict_types=1);

use App\Models\Central\PaymentGateway;
use App\Services\Central\Billing\PaymentGatewayResolver;
use Database\Seeders\Central\PaymentGatewaySeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('KE', 'Kenya', 'KES');
    seedWorldCountry('US', 'United States', 'USD');
    seedWorldCountry('GB', 'United Kingdom', 'GBP');
});

it('resolves from the database catalog by currency priority', function (): void {
    if (! Schema::hasTable('payment_gateways')) {
        $this->markTestSkipped('Payment gateways table is not available.');
    }

    $this->seed(PaymentGatewaySeeder::class);

    $resolver = app(PaymentGatewayResolver::class);

    expect($resolver->resolve('NGN'))->toBe('paystack')
        ->and($resolver->resolve('KES'))->toBe('flutterwave')
        ->and($resolver->resolve('USD', null, 'US'))->toBe('stripe')
        ->and($resolver->resolve('USD', null, null, 'stripe'))->toBe('stripe')
        ->and($resolver->resolve('JPY'))->toBe('paystack');
});

it('throws when the catalog is active but no gateway matches', function (): void {
    if (! Schema::hasTable('payment_gateways')) {
        $this->markTestSkipped('Payment gateways table is not available.');
    }

    $this->seed(PaymentGatewaySeeder::class);

    PaymentGateway::query()->update([
        'is_fallback' => false,
        'is_active' => true,
    ]);
    PaymentGateway::query()->where('slug', '!=', 'stripe')->update(['is_active' => false]);
    PaymentGateway::query()->where('slug', 'stripe')->firstOrFail()
        ->currencies()
        ->detach();

    expect(fn () => app(PaymentGatewayResolver::class)->resolve('JPY'))
        ->toThrow(ValidationException::class);
});

it('lists customer facing options from catalog pivots', function (): void {
    if (! Schema::hasTable('payment_gateways')) {
        $this->markTestSkipped('Payment gateways table is not available.');
    }

    config([
        'payments.stripe.secret' => 'sk_test',
        'payments.paystack.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
    ]);

    $this->seed(PaymentGatewaySeeder::class);

    $resolver = app(PaymentGatewayResolver::class);
    $ngn = collect($resolver->optionsForCurrency('NGN'))->pluck('value')->all();

    expect($ngn)->toContain('paystack', 'flutterwave', 'stripe');

    $kes = collect($resolver->optionsForCurrency('KES'))->pluck('value')->all();

    expect($kes)->toContain('flutterwave', 'stripe')
        ->and($kes)->not->toContain('paystack');
});
