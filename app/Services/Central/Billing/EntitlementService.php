<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanFeatureLimitType;
use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Models\Central\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Resolves and enforces plan feature entitlements for a tenant.
 */
final class EntitlementService
{
    public function __construct(
        private readonly FeatureUsageService $featureUsage,
        private readonly ActiveSubscriptionResolver $subscriptions,
    ) {}

    public function enabled(Tenant $tenant, string $featureKey): bool
    {
        $row = $this->check($tenant, $featureKey);

        return (bool) ($row['enabled'] ?? false);
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     enabled: bool,
     *     used: int,
     *     limit: int|null,
     *     unlimited: bool,
     *     remaining: int|null,
     *     tracks_usage: bool,
     *     limit_type: string|null
     * }
     */
    public function check(Tenant $tenant, string $featureKey): array
    {
        $feature = Feature::query()->where('key', $featureKey)->first();

        if ($feature === null) {
            return [
                'key' => $featureKey,
                'name' => $featureKey,
                'enabled' => false,
                'used' => 0,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
                'tracks_usage' => false,
                'limit_type' => null,
            ];
        }

        try {
            $plan = $this->resolvePlan($tenant);
        } catch (ValidationException) {
            return [
                'key' => $feature->key,
                'name' => $feature->name,
                'enabled' => false,
                'used' => 0,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
                'tracks_usage' => false,
                'limit_type' => $feature->default_limit_type?->value,
            ];
        }

        $summaries = $this->featureUsage->summariesForPlan($tenant, $plan, collect([$feature]));
        $summary = $summaries[$feature->id] ?? $this->featureUsage->summary($tenant, $feature, $plan);

        $pivot = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->first();

        return [
            'key' => $feature->key,
            'name' => $feature->name,
            'enabled' => $summary['enabled'],
            'used' => $summary['used'],
            'limit' => $summary['limit'],
            'unlimited' => $summary['unlimited'],
            'remaining' => $summary['remaining'],
            'tracks_usage' => $summary['tracks_usage'],
            'limit_type' => $pivot?->limit_type?->value ?? $feature->default_limit_type?->value,
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     enabled: bool,
     *     used: int,
     *     limit: int|null,
     *     unlimited: bool,
     *     remaining: int|null,
     *     tracks_usage: bool,
     *     limit_type: string|null
     * }>
     */
    public function summaryForTenant(Tenant $tenant): array
    {
        try {
            $plan = $this->resolvePlan($tenant);
        } catch (ValidationException) {
            return [];
        }

        $plan->loadMissing(['features']);

        /** @var Collection<int, Feature> $features */
        $features = $plan->features;
        $summaries = $this->featureUsage->summariesForPlan($tenant, $plan, $features);

        $rows = [];

        foreach ($features as $feature) {
            $summary = $summaries[$feature->id] ?? [
                'used' => 0,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
                'enabled' => false,
                'tracks_usage' => false,
            ];

            /** @var PlanFeature|null $pivot */
            $pivot = $feature->pivot;

            $rows[] = [
                'key' => $feature->key,
                'name' => $feature->name,
                'enabled' => $summary['enabled'],
                'used' => $summary['used'],
                'limit' => $summary['limit'],
                'unlimited' => $summary['unlimited'],
                'remaining' => $summary['remaining'],
                'tracks_usage' => $summary['tracks_usage'],
                'limit_type' => $pivot?->limit_type?->value ?? $feature->default_limit_type?->value,
            ];
        }

        return $rows;
    }

    /**
     * @throws ValidationException
     */
    public function assertCanUse(Tenant $tenant, string $featureKey, int $amount = 1): void
    {
        $feature = Feature::query()->where('key', $featureKey)->first();

        if ($feature === null) {
            throw ValidationException::withMessages([
                'feature' => ["Feature [{$featureKey}] is not configured."],
            ]);
        }

        $plan = $this->resolvePlan($tenant);
        $pivot = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->first();

        if ($pivot === null || ! $pivot->is_enabled) {
            throw ValidationException::withMessages([
                'feature' => ['This feature is not enabled for your subscription plan.'],
            ]);
        }

        if ($pivot->limit_type === PlanFeatureLimitType::BOOLEAN) {
            if ((int) ($pivot->limit_value ?? 0) < 1 && ! $pivot->allowsUnlimited()) {
                throw ValidationException::withMessages([
                    'feature' => ['This feature is not enabled for your subscription plan.'],
                ]);
            }

            return;
        }

        if ($pivot->allowsUnlimited() || ! $pivot->tracks_usage) {
            return;
        }

        $summary = $this->featureUsage->summary($tenant, $feature, $plan);
        $projected = $summary['used'] + $amount;

        if ($summary['limit'] !== null && $projected > $summary['limit']) {
            throw ValidationException::withMessages([
                'usage' => ['Feature usage limit exceeded for this plan.'],
            ]);
        }
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function consume(Tenant $tenant, string $featureKey, int $amount = 1): FeatureUsage
    {
        $this->assertCanUse($tenant, $featureKey, $amount);

        $feature = Feature::query()->where('key', $featureKey)->firstOrFail();

        return $this->featureUsage->record($tenant, $feature, $amount);
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function release(Tenant $tenant, string $featureKey, int $amount = 1): void
    {
        $feature = Feature::query()->where('key', $featureKey)->first();

        if ($feature === null) {
            return;
        }

        $this->featureUsage->release($tenant, $feature, $amount);
    }

    /**
     * @throws ValidationException
     */
    public function resolvePlan(Tenant $tenant): Plan
    {
        return $this->subscriptions->resolvePlan($tenant);
    }
}
