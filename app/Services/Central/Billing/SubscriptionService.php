<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\BillingAddress;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for managing tenant subscription lifecycle operations.
 *
 * Encapsulates subscription creation, renewal, plan changes, pause/resume,
 * cancellation, expiration, and past-due transitions while recording history
 * and generating invoices through {@see InvoiceService}.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly InvoiceService         $invoiceService,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly PlanPriceResolver      $priceResolver,
    )
    {
    }

    /**
     * Paginate subscriptions with optional tenant, status, plan, gateway, and search filters.
     *
     * @param array{tenant_id?: string, status?: string, plan_id?: int, gateway?: string, search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Subscription::query()
            ->with(['tenant', 'plan', 'planPrice'])
            ->when($filters['tenant_id'] ?? null, fn($q, $id) => $q->where('tenant_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['plan_id'] ?? null, fn($q, $planId) => $q->where('plan_id', $planId))
            ->when($filters['gateway'] ?? null, fn($q, $gateway) => $q->where('gateway', $gateway))
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('gateway_subscription_id', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('tenant', function ($tenantQuery) use ($search): void {
                            $tenantQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('plan', function ($planQuery) use ($search): void {
                            $planQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                        });
                })
            )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(?string $tenantId = null, ?string $search = null): array
    {
        return Subscription::query()
            ->with('plan:id,name')
            ->when(filled($tenantId), fn($query) => $query->where('tenant_id', $tenantId))
            ->when(
                filled($search),
                fn($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('id', 'like', "%{$search}%")
                        ->orWhereHas('plan', fn($planQuery) => $planQuery->where('name', 'like', "%{$search}%"));
                }),
            )
            ->latest()
            ->get()
            ->map(fn(Subscription $subscription): array => [
                'value' => (string)$subscription->getKey(),
                'label' => "#{$subscription->getKey()} — " . ($subscription->plan?->name ?? 'Subscription'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     trialing: int,
     *     past_due: int,
     *     cancelled: int,
     *     paused: int,
     *     by_status: array<string, int>,
     *     by_gateway: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = Subscription::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        $byGateway = Subscription::query()
            ->selectRaw('gateway, COUNT(*) as aggregate')
            ->groupBy('gateway')
            ->pluck('aggregate', 'gateway')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'active' => (int)($byStatus[SubscriptionStatus::ACTIVE->value] ?? 0),
            'trialing' => (int)($byStatus[SubscriptionStatus::TRIALING->value] ?? 0),
            'past_due' => (int)($byStatus[SubscriptionStatus::PAST_DUE->value] ?? 0),
            'cancelled' => (int)($byStatus[SubscriptionStatus::CANCELLED->value] ?? 0),
            'paused' => (int)($byStatus[SubscriptionStatus::PAUSED->value] ?? 0),
            'by_status' => $byStatus,
            'by_gateway' => $byGateway,
        ];
    }

    /**
     * Create a new subscription for a tenant and plan.
     *
     * @param array{
     *     tenant_id: string,
     *     plan_id: int,
     *     plan_price_id?: int|null,
     *     country?: string|null,
     *     currency?: string|null,
     *     billing_interval?: string|null,
     *     gateway?: string,
     *     trial_days?: int|null,
     *     billing_address_id?: int|null,
     *     tax_rate?: float,
     *     idempotency_key?: string|null
     * } $data
     * @param User|null $actor
     * @return Subscription
     *
     * @throws ValidationException|Throwable
     */
    public function create(array $data, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($data, $actor): Subscription {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($data['tenant_id']);
            $plan = Plan::query()->lockForUpdate()->findOrFail($data['plan_id']);

            if ($plan->status !== PlanStatus::Active) {
                throw ValidationException::withMessages([
                    'plan_id' => ['The selected plan is not active.'],
                ]);
            }

            if (filled($data['idempotency_key'] ?? null)) {
                $requestHash = hash('sha256', trim((string)$data['idempotency_key']));
                $existingInvoice = $tenant->invoices()
                    ->where('idempotency_key', 'like', "%:request:{$requestHash}")
                    ->first();

                if ($existingInvoice?->subscription_id !== null) {
                    return Subscription::query()
                        ->with(['tenant', 'plan', 'planPrice', 'invoices'])
                        ->findOrFail($existingInvoice->subscription_id);
                }
            }

            $activeExists = Subscription::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', [
                    SubscriptionStatus::ACTIVE->value,
                    SubscriptionStatus::TRIALING->value,
                    SubscriptionStatus::PAST_DUE->value,
                    SubscriptionStatus::PAUSED->value,
                ])
                ->exists();

            if ($activeExists) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['Tenant already has an active subscription.'],
                ]);
            }

            $planPrice = null;

            if (!empty($data['plan_price_id'])) {
                $planPrice = $plan->prices()
                    ->whereKey($data['plan_price_id'])
                    ->where('status', PlanStatus::Active)
                    ->lockForUpdate()
                    ->first();

                if ($planPrice === null) {
                    throw ValidationException::withMessages([
                        'plan_price_id' => ['The selected price is inactive or does not belong to this plan.'],
                    ]);
                }
            } else {
                $planPrice = $this->priceResolver->resolve(
                    $plan,
                    isset($data['country']) ? (string)$data['country'] : null,
                    isset($data['currency']) ? (string)$data['currency'] : null,
                    $data['billing_interval'] ?? null,
                );
            }

            if (!empty($data['billing_address_id'])) {
                $addressExists = BillingAddress::query()
                    ->whereKey($data['billing_address_id'])
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if (!$addressExists) {
                    throw ValidationException::withMessages([
                        'billing_address_id' => ['The selected billing address does not belong to this tenant.'],
                    ]);
                }
            }

            $trialDays = $data['trial_days'] ?? $planPrice->effectiveTrialDays();
            $status = $trialDays > 0 ? SubscriptionStatus::TRIALING : SubscriptionStatus::ACTIVE;
            $starts = now();
            $periodEnd = $this->addInterval($starts, $planPrice->billing_interval);

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'status' => $status,
                'billing_interval' => $planPrice->billing_interval,
                'price' => $planPrice->amount,
                'currency' => $planPrice->currency,
                'gateway' => $this->gatewayResolver->resolve(
                    $planPrice->currency,
                    isset($data['gateway']) ? (string)$data['gateway'] : null,
                ),
                'trial_ends_at' => $trialDays > 0 ? now()->addDays((int)$trialDays) : null,
                'starts_at' => $starts,
                'current_period_start' => $starts,
                'current_period_end' => $periodEnd,
            ]);

            $this->recordHistory($subscription, 'created', null, $status, null, $plan->id, $actor);

            if ($status === SubscriptionStatus::ACTIVE) {
                $this->invoiceService->createForSubscription($subscription, [
                    'billing_address_id' => $data['billing_address_id'] ?? null,
                    'tax_rate' => $data['tax_rate'] ?? 0,
                    'idempotency_key' => $this->invoiceIdempotencyKey(
                        $subscription,
                        'initial',
                        $data['idempotency_key'] ?? null,
                    ),
                ]);
            }

            return $subscription->load(['tenant', 'plan', 'planPrice', 'invoices']);
        });
    }

    /**
     * Advance a start date by the subscription billing interval.
     *
     * @param Carbon $start
     * @param SubscriptionInterval|string|null $interval
     * @return Carbon
     */
    private function addInterval(Carbon $start, SubscriptionInterval|string|null $interval): Carbon
    {
        $interval = $interval instanceof SubscriptionInterval
            ? $interval
            : SubscriptionInterval::tryFrom((string)$interval) ?? SubscriptionInterval::MONTHLY;

        return match ($interval) {
            SubscriptionInterval::MONTHLY => $start->copy()->addMonth(),
            SubscriptionInterval::QUARTERLY => $start->copy()->addMonths(3),
            SubscriptionInterval::YEARLY => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    /**
     * Persist a subscription lifecycle history entry.
     *
     * @param Subscription $subscription
     * @param string $event
     * @param SubscriptionStatus|null $from
     * @param SubscriptionStatus|null $to
     * @param int|null $fromPlanId
     * @param int|null $toPlanId
     * @param User|null $actor
     */
    private function recordHistory(
        Subscription        $subscription,
        string              $event,
        ?SubscriptionStatus $from,
        ?SubscriptionStatus $to,
        ?int                $fromPlanId,
        ?int                $toPlanId,
        ?User               $actor,
    ): void
    {
        $subscription->histories()->create([
            'event' => $event,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'user_id' => $actor?->id,
        ]);
    }

    private function invoiceIdempotencyKey(
        Subscription $subscription,
        string       $event,
        ?string      $clientKey,
    ): string
    {
        if (filled($clientKey)) {
            return "subscription:{$subscription->id}:request:" . hash('sha256', trim($clientKey));
        }

        return "subscription:{$subscription->id}:{$event}";
    }

    /**
     * Renew a subscription for the next billing period.
     *
     * Extends the current period, clears grace and expiration markers, records
     * renewal history, and generates a new invoice.
     *
     * @param Subscription $subscription
     * @param User|null $actor
     * @return Subscription
     *
     * @throws ValidationException
     */
    public function renew(
        Subscription $subscription,
        ?User        $actor = null,
        ?string      $idempotencyKey = null,
    ): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $idempotencyKey): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if (
                filled($idempotencyKey)
                && $subscription->invoices()
                    ->where('idempotency_key', $this->invoiceIdempotencyKey($subscription, '', $idempotencyKey))
                    ->exists()
            ) {
                return $subscription->load(['tenant', 'plan', 'invoices']);
            }

            if (!in_array($subscription->status, [SubscriptionStatus::ACTIVE, SubscriptionStatus::PAST_DUE, SubscriptionStatus::TRIALING], true)) {
                throw ValidationException::withMessages([
                    'subscription' => ['Only active, past due, or trialing subscriptions can be renewed.'],
                ]);
            }

            $from = $subscription->status;
            $start = $subscription->current_period_end?->isFuture()
                ? $subscription->current_period_end->copy()
                : now();
            $end = $this->addInterval($start, $subscription->billing_interval);

            $subscription->update([
                'status' => SubscriptionStatus::ACTIVE,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'grace_ends_at' => null,
                'expired_at' => null,
            ]);

            $this->recordHistory($subscription, 'renewed', $from, SubscriptionStatus::ACTIVE, null, null, $actor);
            $this->invoiceService->createForSubscription($subscription->fresh(), [
                'idempotency_key' => $this->invoiceIdempotencyKey(
                    $subscription,
                    'renewal:' . $start->utc()->format('YmdHis'),
                    $idempotencyKey,
                ),
            ]);

            return $subscription->fresh(['tenant', 'plan', 'invoices']);
        });
    }

    /**
     * Upgrade a subscription to a higher-tier plan.
     *
     * @param array{country?: string|null, currency?: string|null, billing_interval?: string|null, plan_price_id?: int|null, idempotency_key?: string|null} $options
     */
    public function upgrade(Subscription $subscription, Plan $plan, ?User $actor = null, array $options = []): Subscription
    {
        return $this->changePlan($subscription, $plan, 'upgraded', $actor, $options);
    }

    /**
     * Change a subscription to a different plan.
     *
     * @param array{country?: string|null, currency?: string|null, billing_interval?: string|null, plan_price_id?: int|null, idempotency_key?: string|null} $options
     */
    private function changePlan(
        Subscription $subscription,
        Plan         $plan,
        string       $event,
        ?User        $actor,
        array        $options = [],
    ): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $event, $actor, $options): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);

            if (
                filled($options['idempotency_key'] ?? null)
                && $subscription->invoices()
                    ->where(
                        'idempotency_key',
                        $this->invoiceIdempotencyKey($subscription, '', $options['idempotency_key']),
                    )
                    ->exists()
            ) {
                return $subscription->load(['tenant', 'plan', 'planPrice', 'invoices']);
            }

            if ($plan->status !== PlanStatus::Active) {
                throw ValidationException::withMessages([
                    'plan_id' => ['The selected plan is not active.'],
                ]);
            }

            if (!$subscription->status->isActive() && $subscription->status !== SubscriptionStatus::PAUSED) {
                throw ValidationException::withMessages([
                    'subscription' => ['Subscription cannot change plans in its current status.'],
                ]);
            }

            if ($subscription->plan_id === $plan->id && empty($options['plan_price_id'])) {
                throw ValidationException::withMessages([
                    'plan_id' => ['Subscription is already on this plan.'],
                ]);
            }

            if (!empty($options['plan_price_id'])) {
                $planPrice = $plan->prices()
                    ->whereKey($options['plan_price_id'])
                    ->where('status', PlanStatus::Active)
                    ->lockForUpdate()
                    ->first();

                if ($planPrice === null) {
                    throw ValidationException::withMessages([
                        'plan_price_id' => ['The selected price is inactive or does not belong to this plan.'],
                    ]);
                }
            } else {
                $planPrice = $this->priceResolver->resolve(
                    $plan,
                    isset($options['country']) ? (string)$options['country'] : null,
                    isset($options['currency'])
                        ? (string)$options['currency']
                        : (string)$subscription->currency,
                    $options['billing_interval'] ?? $subscription->billing_interval,
                );
            }

            $fromStatus = $subscription->status;
            $fromPlan = $subscription->plan_id;

            $subscription->update([
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'price' => $planPrice->amount,
                'currency' => $planPrice->currency,
                'billing_interval' => $planPrice->billing_interval,
                'gateway' => $this->gatewayResolver->resolve($planPrice->currency),
                'status' => SubscriptionStatus::ACTIVE,
            ]);

            $this->recordHistory($subscription, $event, $fromStatus, SubscriptionStatus::ACTIVE, $fromPlan, $plan->id, $actor);
            $this->invoiceService->createForSubscription($subscription->fresh(), [
                'description' => ucfirst($event) . ' to ' . $plan->name,
                'idempotency_key' => $this->invoiceIdempotencyKey(
                    $subscription,
                    "{$event}:{$fromPlan}:{$plan->id}:" . ($subscription->current_period_start?->utc()->format('YmdHis') ?? 'none'),
                    $options['idempotency_key'] ?? null,
                ),
            ]);

            return $subscription->fresh(['tenant', 'plan', 'planPrice', 'invoices']);
        });
    }

    /**
     * Downgrade a subscription to a lower-tier plan.
     *
     * @param array{country?: string|null, currency?: string|null, billing_interval?: string|null, plan_price_id?: int|null, idempotency_key?: string|null} $options
     */
    public function downgrade(Subscription $subscription, Plan $plan, ?User $actor = null, array $options = []): Subscription
    {
        return $this->changePlan($subscription, $plan, 'downgraded', $actor, $options);
    }

    /**
     * Pause an active subscription.
     *
     * @param Subscription $subscription
     * @param User|null $actor
     * @return Subscription
     *
     * @throws ValidationException
     */
    public function pause(Subscription $subscription, ?User $actor = null): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'subscription' => ['Only active subscriptions can be paused.'],
            ]);
        }

        $from = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::PAUSED,
            'paused_at' => now(),
        ]);

        $this->recordHistory($subscription, 'paused', $from, SubscriptionStatus::PAUSED, null, null, $actor);

        return $subscription->fresh(['tenant', 'plan']);
    }

    /**
     * Resume a paused subscription.
     *
     * @param Subscription $subscription
     * @param User|null $actor
     * @return Subscription
     *
     * @throws ValidationException
     */
    public function resume(Subscription $subscription, ?User $actor = null): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::PAUSED) {
            throw ValidationException::withMessages([
                'subscription' => ['Only paused subscriptions can be resumed.'],
            ]);
        }

        $from = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'paused_at' => null,
        ]);

        $this->recordHistory($subscription, 'resumed', $from, SubscriptionStatus::ACTIVE, null, null, $actor);

        return $subscription->fresh(['tenant', 'plan']);
    }

    /**
     * Cancel a subscription immediately or at period end.
     *
     * @param Subscription $subscription
     * @param bool $immediately
     * @param string|null $reason
     * @param User|null $actor
     * @return Subscription
     *
     * @throws ValidationException
     */
    public function cancel(Subscription $subscription, bool $immediately = false, ?string $reason = null, ?User $actor = null): Subscription
    {
        if (in_array($subscription->status, [SubscriptionStatus::CANCELLED, SubscriptionStatus::EXPIRED], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['Subscription is already cancelled or expired.'],
            ]);
        }

        $from = $subscription->status;

        if ($immediately) {
            $subscription->update([
                'status' => SubscriptionStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancel_at_period_end' => false,
                'ends_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $this->recordHistory($subscription, 'cancelled', $from, SubscriptionStatus::CANCELLED, null, null, $actor);
        } else {
            $subscription->update([
                'cancel_at_period_end' => true,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);
            $this->recordHistory($subscription, 'cancel_scheduled', $from, $from, null, null, $actor);
        }

        return $subscription->fresh(['tenant', 'plan']);
    }

    /**
     * Expire a subscription and clear grace-period markers.
     *
     * @param Subscription $subscription
     * @param User|null $actor
     * @return Subscription
     */
    public function expire(Subscription $subscription, ?User $actor = null): Subscription
    {
        $from = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::EXPIRED,
            'expired_at' => now(),
            'ends_at' => now(),
            'grace_ends_at' => null,
        ]);

        $this->recordHistory($subscription, 'expired', $from, SubscriptionStatus::EXPIRED, null, null, $actor);

        return $subscription->fresh(['tenant', 'plan']);
    }

    /**
     * Mark a subscription as past due and start a grace period.
     *
     * @param Subscription $subscription
     * @param int $graceDays
     * @param User|null $actor
     * @return Subscription
     */
    public function markPastDue(Subscription $subscription, int $graceDays = 3, ?User $actor = null): Subscription
    {
        $from = $subscription->status;
        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_ends_at' => now()->addDays($graceDays),
        ]);

        $this->recordHistory($subscription, 'past_due', $from, SubscriptionStatus::PAST_DUE, null, null, $actor);

        return $subscription->fresh(['tenant', 'plan']);
    }

    /**
     * Activate a subscription after a successful conversion payment.
     *
     * Clears grace / expired markers and records an activation history entry.
     */
    public function activateAfterPayment(Subscription $subscription, ?User $actor = null): Subscription
    {
        $from = $subscription->status;

        if ($from === SubscriptionStatus::ACTIVE) {
            return $subscription->fresh(['tenant', 'plan']);
        }

        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'grace_ends_at' => null,
            'expired_at' => null,
            'ends_at' => null,
            'paused_at' => null,
        ]);

        $this->recordHistory($subscription, 'activated', $from, SubscriptionStatus::ACTIVE, null, null, $actor);

        return $subscription->fresh(['tenant', 'plan']);
    }
}
