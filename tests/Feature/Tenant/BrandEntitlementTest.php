<?php

declare(strict_types=1);

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Tenant\Brand;

beforeEach(function (): void {
    cleanupTenantDatabases();

    $path = database_path('testing.sqlite');
    if (! file_exists($path)) {
        touch($path);
    }

    $this->artisan('migrate:fresh');
});

afterEach(function (): void {
    cleanupTenantDatabases();
});

it('lists entitlements and enforces brands feature limits', function (): void {
    [$tenant, , $domain] = createProvisionedTenant([
        'email' => 'owner@brands.test',
        'domain' => 'brands.test',
    ], withInvite: false);

    $feature = Feature::factory()->countable()->create([
        'key' => 'brands',
        'slug' => 'brands',
        'name' => 'Brands',
        'default_limit_value' => 2,
    ]);

    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'trial_days' => 7,
        'billing_interval' => SubscriptionInterval::MONTHLY,
    ]);

    $plan->features()->sync([
        $feature->id => [
            'limit_type' => PlanFeatureLimitType::COUNT->value,
            'limit_value' => 2,
            'is_unlimited' => false,
            'is_enabled' => true,
            'tracks_usage' => true,
        ],
    ]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::TRIALING,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'price' => 0,
        'currency' => 'NGN',
        'gateway' => 'paystack',
        'starts_at' => now(),
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'trial_ends_at' => now()->addDays(7),
    ]);

    $login = tenantJson($domain, 'POST', '/api/v1/auth/login', [
        'email' => 'owner@brands.test',
        'password' => 'password',
    ])->assertSuccessful();

    $bearer = ['Authorization' => 'Bearer '.$login->json('data.token')];

    tenantJson($domain, 'GET', '/api/v1/entitlements', [], $bearer)
        ->assertSuccessful()
        ->assertJsonPath('data.0.key', 'brands')
        ->assertJsonPath('data.0.limit', 2)
        ->assertJsonPath('data.0.enabled', true);

    tenantJson($domain, 'GET', '/api/v1/auth/me', [], $bearer)
        ->assertSuccessful()
        ->assertJsonPath('data.entitlements.0.key', 'brands');

    tenantJson($domain, 'POST', '/api/v1/brands', [
        'name' => 'Acme Brand',
        'is_visible' => true,
    ], $bearer)->assertCreated()
        ->assertJsonPath('data.name', 'Acme Brand');

    tenantJson($domain, 'POST', '/api/v1/brands', [
        'name' => 'Second Brand',
        'is_visible' => true,
    ], $bearer)->assertCreated();

    tenantJson($domain, 'POST', '/api/v1/brands', [
        'name' => 'Overflow Brand',
        'is_visible' => true,
    ], $bearer)->assertUnprocessable()
        ->assertJsonValidationErrors(['usage']);

    tenancy()->initialize($tenant);
    expect(Brand::query()->count())->toBe(2)
        ->and((int) FeatureUsage::query()->where('feature_id', $feature->id)->value('used'))->toBe(2);
    $brandId = Brand::query()->value('id');
    tenancy()->end();

    tenantJson($domain, 'DELETE', '/api/v1/brands/'.$brandId, [], $bearer)
        ->assertSuccessful();

    expect((int) FeatureUsage::query()->where('feature_id', $feature->id)->value('used'))->toBe(1);

    tenantJson($domain, 'POST', '/api/v1/brands', [
        'name' => 'Replacement Brand',
        'is_visible' => true,
    ], $bearer)->assertCreated();
});
