<?php

declare(strict_types=1);

use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\EntitlementService;

it('returns batched entitlement summaries for a tenant plan', function (): void {
    $tenant = Tenant::factory()->create();
    $plan = Plan::query()->whereHas('features')->first() ?? Plan::factory()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $rows = app(EntitlementService::class)->summaryForTenant($tenant);

    expect($rows)->toBeArray();

    if ($plan->features()->exists()) {
        expect($rows)->not->toBeEmpty()
            ->and($rows[0])->toHaveKeys([
                'key', 'name', 'enabled', 'used', 'limit', 'unlimited', 'remaining', 'tracks_usage', 'limit_type',
            ]);
    }
});

it('checks a missing feature key without throwing', function (): void {
    $tenant = Tenant::factory()->create();

    $row = app(EntitlementService::class)->check($tenant, 'feature-that-does-not-exist');

    expect($row['enabled'])->toBeFalse()
        ->and($row['key'])->toBe('feature-that-does-not-exist');
});
