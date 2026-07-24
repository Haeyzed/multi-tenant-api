<?php

declare(strict_types=1);

use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Models\Central\BillingProfile;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Setting;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionHistory;
use App\Models\Central\Tenant;
use App\Services\Central\Settings\SettingService;

beforeEach(function (): void {
    cleanupTenantDatabases();

    Setting::query()->updateOrCreate(
        ['key' => 'billing.signup_card_verification'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Require Card Verification at Signup',
            'type' => SettingType::BOOLEAN,
            'value' => '0',
            'default_value' => ['value' => false],
        ],
    );
    app(SettingService::class)->forgetCache();
});

afterEach(function (): void {
    cleanupTenantDatabases();
});

it('lists only active public plans for self-serve signup', function (): void {
    $public = Plan::factory()->create([
        'name' => 'Public Pro',
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
        'sort_order' => 1,
    ]);

    Plan::factory()->create([
        'name' => 'Private Plan',
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Private,
    ]);

    Plan::factory()->create([
        'name' => 'Archived Public',
        'status' => PlanStatus::Archived,
        'visibility' => PlanVisibility::Public,
    ]);

    $this->getJson('/api/v1/public/plans')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $public->id)
        ->assertJsonPath('data.0.name', 'Public Pro');
});

it('signs up a tenant on trial with an active owner password and no invoice', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    $plan = Plan::factory()->create([
        'trial_days' => 1,
        'currency' => 'NGN',
        'price' => 15000,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $response = $this->postJson('/api/v1/public/signup', [
        'name' => 'Self Serve Co',
        'email' => 'owner@selfserve.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
        'owner_name' => 'Self Serve Owner',
        'domain' => 'selfserve.test',
    ])->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.tenant.status', TenantStatus::TRIAL->value)
        ->assertJsonPath('data.tenant.email', 'owner@selfserve.test')
        ->assertJsonPath('data.subscription.status', SubscriptionStatus::TRIALING->value)
        ->assertJsonPath('data.subscription.plan_id', $plan->id)
        ->assertJsonPath('data.login.primary_domain', 'selfserve.test');

    $tenantId = $response->json('data.tenant.id');
    $subscriptionId = $response->json('data.subscription.id');

    expect(Tenant::query()->find($tenantId))->not->toBeNull()
        ->and(Subscription::query()->find($subscriptionId)?->status)->toBe(SubscriptionStatus::TRIALING)
        ->and(Subscription::query()->find($subscriptionId)?->currency)->toBe('NGN')
        ->and(Subscription::query()->find($subscriptionId)?->gateway?->value)->toBe('paystack')
        ->and(Subscription::query()->find($subscriptionId)?->plan_price_id)->not->toBeNull()
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->exists())->toBeFalse()
        ->and(Tenant::query()->find($tenantId)?->metadata['signup_source'] ?? null)->toBe('self_serve')
        ->and(Tenant::query()->find($tenantId)?->metadata['billing_country'] ?? null)->toBe('NG')
        ->and(BillingProfile::query()->where('tenant_id', $tenantId)->first()?->country_iso2)->toBe('NG')
        ->and(BillingProfile::query()->where('tenant_id', $tenantId)->first()?->currency)->toBe('NGN')
        ->and(SubscriptionHistory::query()->where('subscription_id', $subscriptionId)->where('event', 'created')->exists())->toBeTrue();

    tenantJson('selfserve.test', 'POST', '/api/v1/auth/login', [
        'email' => 'owner@selfserve.test',
        'password' => 'Password1!',
    ])->assertSuccessful()
        ->assertJsonPath('data.user.email', 'owner@selfserve.test');
});

it('rejects signup for a non-public plan', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Private,
    ]);

    $this->postJson('/api/v1/public/signup', [
        'name' => 'Blocked Co',
        'email' => 'owner@blocked.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

it('rejects duplicate tenant emails on signup', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    Tenant::factory()->create(['email' => 'taken@example.test']);

    $this->postJson('/api/v1/public/signup', [
        'name' => 'Dup Co',
        'email' => 'taken@example.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects weak passwords on signup', function (): void {
    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $this->postJson('/api/v1/public/signup', [
        'name' => 'Weak Co',
        'email' => 'owner@weak.test',
        'password' => 'short',
        'password_confirmation' => 'short',
        'plan_id' => $plan->id,
        'country' => 'NG',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('requires a billing country on signup', function (): void {
    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $this->postJson('/api/v1/public/signup', [
        'name' => 'No Country Co',
        'email' => 'owner@nocountry.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});
