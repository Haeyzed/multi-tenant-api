<?php

declare(strict_types=1);

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\BillingProfile;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\BillingProfileService;
use App\Services\Central\Billing\PlanPriceResolver;
use App\Services\Central\Billing\SubscriptionService;
use Database\Seeders\Central\PaymentGatewaySeeder;
use Illuminate\Support\Facades\Schema;

it('stores and reads a tenant billing profile', function (): void {
    if (! Schema::hasTable('billing_profiles')) {
        $this->markTestSkipped('billing_profiles table is not available.');
    }

    $tenant = Tenant::factory()->create();

    $profile = app(BillingProfileService::class)->update($tenant, [
        'country_iso2' => 'NG',
        'currency' => 'NGN',
        'preferred_gateway' => 'paystack',
    ]);

    expect($tenant->fresh()->billingProfile?->is($profile))->toBeTrue()
        ->and($profile->currency)->toBe('NGN')
        ->and($profile->preferred_gateway)->toBe('paystack');
});

it('prefers billing profile currency when resolving plan prices', function (): void {
    if (! Schema::hasTable('billing_profiles')) {
        $this->markTestSkipped('billing_profiles table is not available.');
    }

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');

    $plan = Plan::factory()->create(['status' => PlanStatus::Active]);
    PlanPrice::query()->where('plan_id', $plan->id)->delete();
    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'currency' => 'NGN',
        'amount' => 10000,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
    ]);
    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'currency' => 'USD',
        'amount' => 29,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
    ]);

    $profile = BillingProfile::factory()->create([
        'currency' => 'NGN',
        'country_iso2' => 'US',
    ]);

    $price = app(PlanPriceResolver::class)->resolve(
        $plan->fresh('prices'),
        'US',
        'USD',
        SubscriptionInterval::MONTHLY,
        $profile,
    );

    expect($price->currency)->toBe('NGN');
});

it('passes billing profile country and preferred gateway into subscription creation', function (): void {
    if (! Schema::hasTable('billing_profiles') || ! Schema::hasTable('payment_gateways')) {
        $this->markTestSkipped('Billing profile or payment gateway tables unavailable.');
    }

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');
    $this->seed(PaymentGatewaySeeder::class);

    $tenant = Tenant::factory()->create();
    BillingProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'country_iso2' => 'US',
        'currency' => 'USD',
        'preferred_gateway' => 'stripe',
    ]);

    $plan = Plan::factory()->create(['status' => PlanStatus::Active, 'trial_days' => 0]);
    PlanPrice::query()->where('plan_id', $plan->id)->delete();
    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'currency' => 'USD',
        'amount' => 29,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
        'trial_days' => 0,
    ]);

    $subscription = app(SubscriptionService::class)->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
    ]);

    expect($subscription->gateway->value)->toBe('stripe')
        ->and($subscription->currency)->toBe('USD');
});
