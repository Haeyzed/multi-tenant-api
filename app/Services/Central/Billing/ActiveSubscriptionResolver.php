<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the tenant subscription that currently grants plan entitlements.
 *
 * Eligible statuses: ACTIVE, TRIALING (within trial window), PAST_DUE (within grace).
 */
final class ActiveSubscriptionResolver
{
    /**
     * Find the latest eligible subscription for the tenant, or null.
     */
    public function find(Tenant $tenant, bool $lockForUpdate = false): ?Subscription
    {
        $query = $this->eligibleQuery()
            ->where('tenant_id', $tenant->id)
            ->latest('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Resolve the plan for the tenant's eligible subscription.
     *
     * @throws ValidationException
     */
    public function resolvePlan(Tenant $tenant, bool $lockForUpdate = false): Plan
    {
        $subscription = $this->find($tenant, $lockForUpdate);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['The tenant does not have an eligible subscription.'],
            ]);
        }

        return Plan::query()->findOrFail($subscription->plan_id);
    }

    /**
     * @return Builder<Subscription>
     */
    public function eligibleQuery(): Builder
    {
        return Subscription::query()
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
            });
    }
}
