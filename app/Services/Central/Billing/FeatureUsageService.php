<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for recording and summarizing tenant feature usage.
 *
 * Tracks usage within billing periods, enforces plan limits before
 * incrementing counters, and exposes current-period consumption summaries.
 */
final class FeatureUsageService
{
    public function __construct(
        private readonly ActiveSubscriptionResolver $subscriptions,
    ) {}

    /**
     * Record feature usage for a tenant within the current billing period.
     *
     * Creates or increments a usage counter and rejects the operation when
     * the projected total would exceed the plan's configured limit.
     *
     *
     * @throws ValidationException|Throwable
     */
    public function record(Tenant $tenant, Feature $feature, int $amount = 1, ?Plan $plan = null): FeatureUsage
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Usage amount must be at least 1.'],
            ]);
        }

        return DB::connection($this->centralConnection())->transaction(function () use ($tenant, $feature, $amount, $plan): FeatureUsage {
            $entitledPlan = $this->subscriptions->resolvePlan($tenant, lockForUpdate: true);

            if ($plan !== null && $plan->id !== $entitledPlan->id) {
                throw ValidationException::withMessages([
                    'plan_id' => ['The submitted plan does not match the tenant active subscription.'],
                ]);
            }

            $pivot = PlanFeature::query()
                ->where('plan_id', $entitledPlan->id)
                ->where('feature_id', $feature->id)
                ->lockForUpdate()
                ->first();

            if ($pivot === null || ! $pivot->is_enabled) {
                throw ValidationException::withMessages([
                    'feature_id' => ['This feature is not enabled for the tenant subscription plan.'],
                ]);
            }

            [$starts, $ends] = $this->currentPeriod($entitledPlan, $pivot);
            $now = now();

            FeatureUsage::query()->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
                'plan_id' => $entitledPlan->id,
                'used' => 0,
                'period_starts_at' => $starts,
                'period_ends_at' => $ends,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var FeatureUsage $usage */
            $usage = FeatureUsage::query()
                ->where('tenant_id', $tenant->id)
                ->where('feature_id', $feature->id)
                ->where('period_starts_at', $starts)
                ->lockForUpdate()
                ->firstOrFail();

            $projected = $usage->used + $amount;

            if (
                ! $pivot->allowsUnlimited()
                && $pivot->limit_type !== PlanFeatureLimitType::BOOLEAN
                && $pivot->limit_value !== null
                && $projected > $pivot->limit_value
            ) {
                throw ValidationException::withMessages([
                    'usage' => ['Feature usage limit exceeded for this plan.'],
                ]);
            }

            $usage->update([
                'plan_id' => $entitledPlan->id,
                'period_ends_at' => $ends,
                'used' => $projected,
            ]);

            return $usage->fresh();
        }, attempts: 3);
    }

    /**
     * Decrement tracked feature usage for the current billing period.
     *
     * @throws Throwable
     */
    public function release(Tenant $tenant, Feature $feature, int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        DB::connection($this->centralConnection())->transaction(function () use ($tenant, $feature, $amount): void {
            try {
                $entitledPlan = $this->subscriptions->resolvePlan($tenant, lockForUpdate: true);
            } catch (ValidationException) {
                return;
            }

            $pivot = PlanFeature::query()
                ->where('plan_id', $entitledPlan->id)
                ->where('feature_id', $feature->id)
                ->lockForUpdate()
                ->first();

            if ($pivot === null || ! $pivot->tracks_usage) {
                return;
            }

            [$starts] = $this->currentPeriod($entitledPlan, $pivot);

            /** @var FeatureUsage|null $usage */
            $usage = FeatureUsage::query()
                ->where('tenant_id', $tenant->id)
                ->where('feature_id', $feature->id)
                ->where('period_starts_at', $starts)
                ->lockForUpdate()
                ->first();

            if ($usage === null) {
                return;
            }

            $usage->update([
                'used' => max(0, (int) $usage->used - $amount),
            ]);
        }, attempts: 3);
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * Resolve the start and end timestamps for the current usage period.
     *
     * Uses the plan-feature reset period when available, otherwise falls
     * back to the plan billing interval to choose monthly or yearly bounds.
     *
     * @param  Plan|null  $plan
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentPeriod(Plan $plan, PlanFeature $pivot): array
    {
        $reset = $pivot->reset_period ?? $plan->billing_interval;

        return match ($reset) {
            SubscriptionInterval::QUARTERLY => [now()->startOfQuarter(), now()->endOfQuarter()],
            SubscriptionInterval::YEARLY => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * Summarize current-period usage for a tenant feature on a plan.
     *
     * @return array{used: int, limit: int|null, unlimited: bool, remaining: int|null, enabled: bool, tracks_usage: bool}
     */
    public function summary(Tenant $tenant, Feature $feature, Plan $plan): array
    {
        $summaries = $this->summariesForPlan($tenant, $plan, collect([$feature]));

        return $summaries[$feature->id] ?? [
            'used' => 0,
            'limit' => 0,
            'unlimited' => false,
            'remaining' => 0,
            'enabled' => false,
            'tracks_usage' => false,
        ];
    }

    /**
     * Batch-summarize usage for many features on one plan (avoids N+1).
     *
     * @param  Collection<int, Feature>  $features
     * @return array<int, array{used: int, limit: int|null, unlimited: bool, remaining: int|null, enabled: bool, tracks_usage: bool}>
     */
    public function summariesForPlan(Tenant $tenant, Plan $plan, Collection $features): array
    {
        if ($features->isEmpty()) {
            return [];
        }

        $featureIds = $features->pluck('id')->all();

        /** @var Collection<int, PlanFeature> $pivots */
        $pivots = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->whereIn('feature_id', $featureIds)
            ->get()
            ->keyBy('feature_id');

        $periodStartsByFeature = [];
        foreach ($features as $feature) {
            $pivot = $pivots->get($feature->id);
            if ($pivot !== null) {
                [$starts] = $this->currentPeriod($plan, $pivot);
                $periodStartsByFeature[$feature->id] = $starts->toDateTimeString();
            }
        }

        $usageByFeature = [];
        if ($periodStartsByFeature !== []) {
            $usages = FeatureUsage::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('feature_id', array_keys($periodStartsByFeature))
                ->whereIn('period_starts_at', array_unique(array_values($periodStartsByFeature)))
                ->get(['feature_id', 'period_starts_at', 'used']);

            foreach ($usages as $usage) {
                $expectedStart = $periodStartsByFeature[$usage->feature_id] ?? null;
                $actualStart = $usage->period_starts_at instanceof \DateTimeInterface
                    ? $usage->period_starts_at->format('Y-m-d H:i:s')
                    : (string) $usage->period_starts_at;

                if ($expectedStart !== null && $actualStart === $expectedStart) {
                    $usageByFeature[$usage->feature_id] = (int) $usage->used;
                }
            }
        }

        $summaries = [];

        foreach ($features as $feature) {
            $pivot = $pivots->get($feature->id);

            if ($pivot === null) {
                $summaries[$feature->id] = [
                    'used' => 0,
                    'limit' => 0,
                    'unlimited' => false,
                    'remaining' => 0,
                    'enabled' => false,
                    'tracks_usage' => false,
                ];

                continue;
            }

            $used = $usageByFeature[$feature->id] ?? 0;
            $unlimited = $pivot->allowsUnlimited();
            $limit = $unlimited ? null : $pivot->limit_value;

            $summaries[$feature->id] = [
                'used' => $used,
                'limit' => $limit,
                'unlimited' => $unlimited,
                'remaining' => $unlimited || $limit === null ? null : max(0, $limit - $used),
                'enabled' => (bool) $pivot->is_enabled,
                'tracks_usage' => (bool) $pivot->tracks_usage,
            ];
        }

        return $summaries;
    }
}
