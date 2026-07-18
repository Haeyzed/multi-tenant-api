<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;
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
    /**
     * Record feature usage for a tenant within the current billing period.
     *
     * Creates or increments a usage counter and rejects the operation when
     * the projected total would exceed the plan's configured limit.
     *
     * @param Tenant $tenant
     * @param Feature $feature
     * @param int $amount
     * @param Plan|null $plan
     * @return FeatureUsage
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

        return DB::transaction(function () use ($tenant, $feature, $amount, $plan): FeatureUsage {
            $entitledPlan = $this->entitledPlan($tenant);

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

            if ($pivot === null || !$pivot->is_enabled) {
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
                !$pivot->allowsUnlimited()
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

    private function entitledPlan(Tenant $tenant): Plan
    {
        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query): void {
                $query->where('status', SubscriptionStatus::ACTIVE)
                    ->orWhere(function ($trialing): void {
                        $trialing->where('status', SubscriptionStatus::TRIALING)
                            ->where(function ($trial): void {
                                $trial->whereNull('trial_ends_at')
                                    ->orWhere('trial_ends_at', '>', now());
                            });
                    })
                    ->orWhere(function ($pastDue): void {
                        $pastDue->where('status', SubscriptionStatus::PAST_DUE)
                            ->where('grace_ends_at', '>', now());
                    });
            })
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['The tenant does not have an eligible subscription.'],
            ]);
        }

        return Plan::query()->findOrFail($subscription->plan_id);
    }

    /**
     * Resolve the start and end timestamps for the current usage period.
     *
     * Uses the plan-feature reset period when available, otherwise falls
     * back to the plan billing interval to choose monthly or yearly bounds.
     *
     * @param Plan|null $plan
     * @param PlanFeature $pivot
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
     * @param Tenant $tenant
     * @param Feature $feature
     * @param Plan $plan
     * @return array{used: int, limit: int|null, unlimited: bool, remaining: int|null, enabled: bool, tracks_usage: bool}
     */
    public function summary(Tenant $tenant, Feature $feature, Plan $plan): array
    {
        $pivot = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->first();

        if (!$pivot) {
            return [
                'used' => 0,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
                'enabled' => false,
                'tracks_usage' => false,
            ];
        }

        [$starts] = $this->currentPeriod($plan, $pivot);

        $used = (int)FeatureUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->where('period_starts_at', $starts)
            ->value('used');

        $unlimited = $pivot->allowsUnlimited();
        $limit = $unlimited ? null : $pivot->limit_value;

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $unlimited,
            'remaining' => $unlimited || $limit === null ? null : max(0, $limit - $used),
            'enabled' => (bool)$pivot->is_enabled,
            'tracks_usage' => (bool)$pivot->tracks_usage,
        ];
    }
}
