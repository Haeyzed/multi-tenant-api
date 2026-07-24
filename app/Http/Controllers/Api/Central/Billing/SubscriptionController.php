<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Central\Billing\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Central\Billing\MarkSubscriptionPastDueRequest;
use App\Http\Requests\Central\Billing\StoreSubscriptionRequest;
use App\Http\Requests\Central\Billing\SubscriptionOptionsRequest;
use App\Http\Resources\Central\SubscriptionHistoryResource;
use App\Http\Resources\Central\SubscriptionResource;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\User;
use App\Services\Central\Billing\BillingSettings;
use App\Services\Central\Billing\SubscriptionService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Subscriptions', description: 'Subscription lifecycle.', weight: 130)]
final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly BillingSettings $billingSettings,
    ) {}

    #[Endpoint(operationId: 'billing.subscription.index', title: 'List subscriptions', description: 'Return a paginated list of subscriptions.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);
        $subscriptions = $this->subscriptionService->paginate($request->only([
            'tenant_id', 'status', 'plan_id', 'gateway', 'search', 'start_date', 'end_date', 'per_page',
        ]));

        return $this->paginated(SubscriptionResource::collection($subscriptions), 'Subscriptions retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.subscription.options', title: 'Subscription options', description: 'Return all subscription value/label pairs for comboboxes.')]
    public function options(SubscriptionOptionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->success(
            $this->subscriptionService->options(
                filled($data['tenant_id'] ?? null) ? (string) $data['tenant_id'] : null,
                filled($data['search'] ?? null) ? (string) $data['search'] : null,
            ),
            'Subscription options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'billing.subscription.statistics', title: 'Subscription statistics', description: 'Return subscription overview statistics.')]
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return $this->success(
            $this->subscriptionService->overviewStatistics(),
            'Subscription statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.store', title: 'Create subscription', description: 'Create a new subscription and return it.')]
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['billing_interval'] ??= $this->billingSettings->defaultInterval()->value;
        $data['idempotency_key'] = $request->header('Idempotency-Key');

        /** @var User $user */
        $user = $request->user();
        $subscription = $this->subscriptionService->create($data, $user);

        return $this->success(new SubscriptionResource($subscription), 'Subscription created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.subscription.show', title: 'Show subscription', description: 'Return a single subscription by ID.')]
    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);
        $subscription->load(['tenant', 'plan', 'planPrice', 'histories', 'invoices.items']);

        return $this->success(new SubscriptionResource($subscription), 'Subscription retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.subscription.renew', title: 'Renew subscription', description: 'Advance the subscription billing period.')]
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->renew(
                $subscription,
                $user,
                $request->header('Idempotency-Key'),
            )),
            'Subscription renewed successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.upgrade', title: 'Upgrade subscription', description: 'Move the subscription to a higher plan.')]
    public function upgrade(ChangeSubscriptionPlanRequest $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validated();
        $plan = Plan::query()->findOrFail($data['plan_id']);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->upgrade(
                $subscription,
                $plan,
                $user,
                [
                    ...collect($data)->except('plan_id')->all(),
                    'idempotency_key' => $request->header('Idempotency-Key'),
                ],
            )),
            'Subscription upgraded successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.downgrade', title: 'Downgrade subscription', description: 'Move the subscription to a lower plan.')]
    public function downgrade(ChangeSubscriptionPlanRequest $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validated();
        $plan = Plan::query()->findOrFail($data['plan_id']);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->downgrade(
                $subscription,
                $plan,
                $user,
                [
                    ...collect($data)->except('plan_id')->all(),
                    'idempotency_key' => $request->header('Idempotency-Key'),
                ],
            )),
            'Subscription downgraded successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.pause', title: 'Pause subscription', description: 'Pause billing and access temporarily.')]
    public function pause(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->pause($subscription, $user)),
            'Subscription paused successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.resume', title: 'Resume subscription', description: 'Resume a paused subscription.')]
    public function resume(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->resume($subscription, $user)),
            'Subscription resumed successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.cancel', title: 'Cancel subscription', description: 'Cancel a scheduled, draft, or active subscription.')]
    public function cancel(CancelSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validated();
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->cancel(
                $subscription,
                (bool) ($data['immediately'] ?? false),
                $data['reason'] ?? null,
                $user
            )),
            'Subscription cancelled successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.expire', title: 'Expire subscription', description: 'Mark the subscription as expired.')]
    public function expire(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('manage', $subscription);
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->expire($subscription, $user)),
            'Subscription expired successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.markPastDue', title: 'Mark past due', description: 'Mark past due and optionally start grace period.')]
    public function markPastDue(MarkSubscriptionPastDueRequest $request, Subscription $subscription): JsonResponse
    {
        $data = $request->validated();
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            new SubscriptionResource($this->subscriptionService->markPastDue(
                $subscription,
                (int) ($data['grace_days'] ?? $this->billingSettings->pastDueGraceDays()),
                $user
            )),
            'Subscription marked past due.'
        );
    }

    #[Endpoint(operationId: 'billing.subscription.history', title: 'subscription history', description: 'Paginate history events for this subscription.')]
    public function history(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return $this->success(
            SubscriptionHistoryResource::collection($subscription->histories()->latest()->get()),
            'Subscription history retrieved successfully.'
        );
    }
}
