<?php

declare(strict_types=1);

use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\ActiveSubscriptionResolver;
use Illuminate\Validation\ValidationException;

it('resolves the plan for an active subscription', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $resolved = app(ActiveSubscriptionResolver::class)->resolvePlan($tenant);

    expect($resolved->id)->toBe($plan->id);
});

it('rejects tenants without an eligible subscription', function (): void {
    $tenant = Tenant::factory()->create();

    app(ActiveSubscriptionResolver::class)->resolvePlan($tenant);
})->throws(ValidationException::class);
