<?php

declare(strict_types=1);

use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Dashboard\DashboardService;

it('calculates mrr and arr from recurring subscriptions', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'price' => 30,
        'billing_interval' => SubscriptionInterval::MONTHLY,
    ]);

    Subscription::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'price' => 120,
        'billing_interval' => SubscriptionInterval::YEARLY,
    ]);

    $revenue = app(DashboardService::class)->revenue();

    expect($revenue['mrr'])->toBe(40.0)
        ->and($revenue['arr'])->toBe(480.0)
        ->and($revenue['recurring_subscriptions'])->toBe(2);
});
