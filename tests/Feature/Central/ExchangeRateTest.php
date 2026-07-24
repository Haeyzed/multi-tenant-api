<?php

declare(strict_types=1);

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\ExchangeRate;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Services\Central\Billing\ExchangeRateService;
use App\Services\Central\Billing\PlanPriceResolver;
use Illuminate\Support\Facades\Schema;

it('converts amounts for reporting without affecting plan price resolution', function (): void {
    if (! Schema::hasTable('exchange_rates')) {
        $this->markTestSkipped('exchange_rates table is not available.');
    }

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');

    ExchangeRate::query()->create([
        'base_currency' => 'USD',
        'quote_currency' => 'NGN',
        'rate' => 1500,
        'source' => 'test',
        'observed_at' => now(),
    ]);

    $fx = app(ExchangeRateService::class);

    expect($fx->getRate('USD', 'NGN'))->toBe(1500.0)
        ->and($fx->convert(10, 'USD', 'NGN'))->toBe(15000.0);

    $plan = Plan::factory()->create(['status' => PlanStatus::Active]);
    PlanPrice::query()->where('plan_id', $plan->id)->delete();
    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'currency' => 'USD',
        'amount' => 29,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
    ]);

    $price = app(PlanPriceResolver::class)->resolve($plan->fresh('prices'), 'US');

    expect($price->currency)->toBe('USD')
        ->and((float) $price->amount)->toBe(29.0);
});
