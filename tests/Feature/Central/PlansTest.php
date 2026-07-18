<?php

declare(strict_types=1);

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;

it('creates plans with pricing trials and feature assignments', function (): void {
    actingAsCentralUser([
        'plans.view',
        'plans.create',
        'plans.update',
        'plans.delete',
        'plans.restore',
        'plans.manage-features',
        'plans.view-usage',
        'plans.record-usage',
    ]);

    $feature = Feature::factory()->countable()->create([
        'key' => 'products_limit',
        'name' => 'Products',
    ]);

    $booleanFeature = Feature::factory()->create([
        'key' => 'custom_domain',
        'name' => 'Custom Domain',
        'default_limit_type' => PlanFeatureLimitType::BOOLEAN,
    ]);

    $created = $this->postJson('/api/v1/plans', [
        'name' => 'Growth',
        'price' => 49.99,
        'currency' => 'USD',
        'billing_interval' => SubscriptionInterval::MONTHLY->value,
        'trial_days' => 14,
        'status' => PlanStatus::Active->value,
        'visibility' => PlanVisibility::Public->value,
        'features' => [
            [
                'feature_id' => $feature->id,
                'limit_type' => PlanFeatureLimitType::COUNT->value,
                'limit_value' => 500,
                'tracks_usage' => true,
            ],
            [
                'feature_id' => $booleanFeature->id,
                'limit_type' => PlanFeatureLimitType::BOOLEAN->value,
                'is_enabled' => true,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Growth')
        ->assertJsonPath('data.features_count', 2)
        ->assertJsonStructure([
            'data' => ['created_at', 'created_at_human', 'updated_at', 'updated_at_human'],
        ]);

    $planId = $created->json('data.id');

    $this->getJson("/api/v1/plans/{$planId}")
        ->assertSuccessful()
        ->assertJsonPath('data.is_publicly_visible', true);

    $unlimited = Feature::factory()->create([
        'key' => 'storage',
        'default_limit_type' => PlanFeatureLimitType::STORAGE,
    ]);

    $this->postJson("/api/v1/plans/{$planId}/features", [
        'feature_id' => $unlimited->id,
        'limit_type' => PlanFeatureLimitType::UNLIMITED->value,
        'is_unlimited' => true,
        'tracks_usage' => false,
    ])->assertCreated()
        ->assertJsonPath('data.is_unlimited', true);

    $this->putJson("/api/v1/plans/{$planId}/features", [
        'features' => [
            [
                'feature_id' => $feature->id,
                'limit_type' => PlanFeatureLimitType::COUNT->value,
                'limit_value' => 1000,
                'tracks_usage' => true,
            ],
        ],
    ])->assertSuccessful()
        ->assertJsonPath('data.features_count', 1);

    $tenant = Tenant::factory()->create();
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $planId,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->postJson('/api/v1/feature-usages', [
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'plan_id' => $planId,
        'amount' => 3,
    ])->assertCreated()
        ->assertJsonPath('data.used', 3);

    $this->getJson("/api/v1/plans/{$planId}/features/{$feature->id}/tenants/{$tenant->id}/usage")
        ->assertSuccessful()
        ->assertJsonPath('data.used', 3)
        ->assertJsonPath('data.limit', 1000);

    $this->postJson("/api/v1/plans/{$planId}/archive")
        ->assertSuccessful()
        ->assertJsonPath('data.status', PlanStatus::Archived->value);

    $this->deleteJson("/api/v1/plans/{$planId}")
        ->assertSuccessful();

    expect(Plan::withTrashed()->find($planId)?->trashed())->toBeTrue();
});

it('enforces feature usage limits', function (): void {
    actingAsCentralUser(['plans.record-usage', 'plans.manage-features', 'plans.create', 'plans.view']);

    $feature = Feature::factory()->countable()->create();
    $plan = Plan::factory()->create();

    $this->postJson("/api/v1/plans/{$plan->id}/features", [
        'feature_id' => $feature->id,
        'limit_type' => PlanFeatureLimitType::COUNT->value,
        'limit_value' => 2,
        'tracks_usage' => true,
    ])->assertCreated();

    $tenant = Tenant::factory()->create();
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->postJson('/api/v1/feature-usages', [
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'plan_id' => $plan->id,
        'amount' => 2,
    ])->assertCreated();

    $this->postJson('/api/v1/feature-usages', [
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'plan_id' => $plan->id,
        'amount' => 1,
    ])->assertUnprocessable();
});

it('derives usage entitlement from the eligible subscription and honors quarterly resets', function (): void {
    actingAsCentralUser(['plans.record-usage', 'plans.manage-features', 'plans.view']);
    $this->travelTo(now()->setDate(2026, 5, 15)->setTime(12, 0));

    $feature = Feature::factory()->countable()->create();
    $entitledPlan = Plan::factory()->create();
    $otherPlan = Plan::factory()->create();
    $tenant = Tenant::factory()->create();

    $entitledPlan->features()->attach($feature->id, [
        'limit_type' => PlanFeatureLimitType::COUNT->value,
        'limit_value' => 10,
        'is_enabled' => true,
        'tracks_usage' => true,
        'reset_period' => SubscriptionInterval::QUARTERLY->value,
    ]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $entitledPlan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->postJson('/api/v1/feature-usages', [
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'plan_id' => $otherPlan->id,
        'amount' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);

    $this->postJson('/api/v1/feature-usages', [
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'plan_id' => $entitledPlan->id,
        'amount' => 2,
    ])->assertCreated();

    $usage = FeatureUsage::query()->firstOrFail();

    expect($usage->plan_id)->toBe($entitledPlan->id)
        ->and($usage->period_starts_at?->toDateString())->toBe('2026-04-01')
        ->and($usage->period_ends_at?->toDateString())->toBe('2026-06-30');
});

it('bulk deletes activates and archives plans', function (): void {
    actingAsCentralUser(['plans.delete', 'plans.update', 'plans.view']);

    $toDelete = Plan::factory()->count(2)->create();
    $toArchive = Plan::factory()->count(2)->create(['status' => PlanStatus::Active]);
    $toActivate = Plan::factory()->count(2)->create(['status' => PlanStatus::Archived]);

    $this->deleteJson('/api/v1/plans/bulk', [
        'ids' => $toDelete->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);

    expect(Plan::onlyTrashed()->whereIn('id', $toDelete->pluck('id'))->count())->toBe(2);

    $this->postJson('/api/v1/plans/bulk/archive', [
        'ids' => $toArchive->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.archived', 2);

    expect(
        Plan::query()
            ->whereIn('id', $toArchive->pluck('id'))
            ->where('status', PlanStatus::Archived)
            ->count()
    )->toBe(2);

    $this->postJson('/api/v1/plans/bulk/activate', [
        'ids' => $toActivate->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.activated', 2);

    expect(
        Plan::query()
            ->whereIn('id', $toActivate->pluck('id'))
            ->where('status', PlanStatus::Active)
            ->count()
    )->toBe(2);
});

it('forbids bulk plan actions without permission', function (): void {
    actingAsCentralUser(['plans.view']);
    $plans = Plan::factory()->count(2)->create();

    $this->deleteJson('/api/v1/plans/bulk', [
        'ids' => $plans->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/plans/bulk/activate', [
        'ids' => $plans->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/plans/bulk/archive', [
        'ids' => $plans->pluck('id')->all(),
    ])->assertForbidden();
});

it('returns plan overview statistics', function (): void {
    actingAsCentralUser(['plans.view']);

    Plan::factory()->create(['status' => PlanStatus::Active, 'visibility' => PlanVisibility::Public, 'is_featured' => true]);
    Plan::factory()->create(['status' => PlanStatus::Draft, 'visibility' => PlanVisibility::Private]);
    Plan::factory()->create(['status' => PlanStatus::Archived, 'visibility' => PlanVisibility::Hidden]);

    $this->getJson('/api/v1/plans/statistics')
        ->assertSuccessful()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.active', 1)
        ->assertJsonPath('data.draft', 1)
        ->assertJsonPath('data.archived', 1)
        ->assertJsonPath('data.public', 1)
        ->assertJsonPath('data.featured', 1);
});
