<?php

declare(strict_types=1);

use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Setting;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('central.settings.map');

    Setting::query()->updateOrCreate(
        ['key' => 'billing.default_currency'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Default Billing Currency',
            'type' => SettingType::STRING,
            'value' => 'USD',
            'default_value' => ['value' => 'USD'],
            'sort_order' => 3,
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
            ], JSON_THROW_ON_ERROR),
            'default_value' => ['value' => [
                'NGN' => 'paystack',
                'USD' => 'stripe',
            ]],
            'sort_order' => 2,
        ],
    );

    app(SettingService::class)->forgetCache();
    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');
});

it('includes plan_price_id on subscription payloads', function (): void {
    actingAsCentralUser(['subscriptions.view']);

    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
        'price' => 29,
        'currency' => 'USD',
    ]);

    PlanPrice::query()->where('plan_id', $plan->id)->delete();

    $usd = PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'amount' => 29,
        'currency' => 'USD',
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
    ]);

    $tenant = Tenant::factory()->create();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'plan_price_id' => $usd->id,
        'price' => $usd->amount,
        'currency' => $usd->currency,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->getJson("/api/v1/subscriptions/{$subscription->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.plan_price_id', $usd->id)
        ->assertJsonPath('data.plan_price.id', $usd->id)
        ->assertJsonPath('data.plan_price.currency', 'USD');
});
