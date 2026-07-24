<?php

declare(strict_types=1);

use App\Models\Central\PaymentGateway;
use App\Models\World\Country;
use App\Models\World\Currency;
use Database\Seeders\Central\PaymentGatewaySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('GH', 'Ghana', 'GHS');
    seedWorldCountry('ZA', 'South Africa', 'ZAR');
    seedWorldCountry('KE', 'Kenya', 'KES');
    seedWorldCountry('US', 'United States', 'USD');
    seedWorldCountry('GB', 'United Kingdom', 'GBP');
    seedWorldCountry('CA', 'Canada', 'CAD');
    seedWorldCountry('AU', 'Australia', 'AUD');

    // Extra currency codes that share no dedicated country fixture still need rows.
    foreach (['UGX', 'TZS', 'XAF', 'XOF', 'EUR'] as $code) {
        $country = Country::query()->where('iso2', 'US')->first();
        if ($country === null || ! Schema::hasTable('currencies')) {
            continue;
        }

        Currency::query()->updateOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            [
                'name' => $code.' Currency',
                'precision' => 2,
                'symbol' => $code,
                'symbol_native' => $code,
                'symbol_first' => true,
                'decimal_mark' => '.',
                'thousands_separator' => ',',
            ],
        );
    }
});

it('seeds stripe paystack and flutterwave with currency and country pivots', function (): void {
    if (! Schema::hasTable('payment_gateways') || ! Schema::hasTable('currencies')) {
        $this->markTestSkipped('Payment gateway or world tables are not available.');
    }

    $this->seed(PaymentGatewaySeeder::class);

    $gateways = PaymentGateway::query()->orderBy('priority')->get();

    expect($gateways)->toHaveCount(3)
        ->and($gateways->pluck('slug')->all())->toBe(['paystack', 'flutterwave', 'stripe']);

    $paystack = PaymentGateway::query()->where('slug', 'paystack')->firstOrFail();

    expect($paystack->is_active)->toBeTrue()
        ->and($paystack->is_fallback)->toBeTrue()
        ->and($paystack->supports_refund)->toBeTrue()
        ->and($paystack->currencies()->pluck('code')->unique()->sort()->values()->all())
        ->toEqualCanonicalizing(['GHS', 'NGN', 'USD', 'ZAR'])
        ->and($paystack->countries()->pluck('iso2')->sort()->values()->all())
        ->toEqualCanonicalizing(['GH', 'NG', 'ZA']);

    $stripe = PaymentGateway::query()->where('slug', 'stripe')->firstOrFail();

    expect($stripe->currencies()->where('code', 'USD')->exists())->toBeTrue()
        ->and($stripe->countries()->where('iso2', 'US')->exists())->toBeTrue();
});

it('can create a gateway via factory', function (): void {
    $gateway = PaymentGateway::factory()->create([
        'slug' => 'manual_test',
        'driver' => 'manual',
    ]);

    expect($gateway->slug)->toBe('manual_test')
        ->and($gateway->is_active)->toBeTrue();
});
