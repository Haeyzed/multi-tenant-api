<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plans\AssignPlanFeatureRequest;
use App\Http\Requests\Central\Plans\BulkActivatePlansRequest;
use App\Http\Requests\Central\Plans\BulkArchivePlansRequest;
use App\Http\Requests\Central\Plans\BulkDeletePlansRequest;
use App\Http\Requests\Central\Plans\RecordFeatureUsageRequest;
use App\Http\Requests\Central\Plans\StorePlanPriceRequest;
use App\Http\Requests\Central\Plans\StorePlanRequest;
use App\Http\Requests\Central\Plans\SyncPlanFeaturesRequest;
use App\Http\Requests\Central\Plans\UpdatePlanPriceRequest;
use App\Http\Requests\Central\Plans\UpdatePlanRequest;
use App\Http\Resources\Central\PlanFeatureResource;
use App\Http\Resources\Central\PlanPriceResource;
use App\Http\Resources\Central\PlanResource;
use App\Models\Central\Feature;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\FeatureUsageService;
use App\Services\Central\Billing\PlanService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Plans', description: 'Plans, plan features, usage.', weight: 120)]
final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly FeatureUsageService $featureUsageService,
    ) {}

    #[Endpoint(operationId: 'billing.plan.index', title: 'List plans', description: 'Return a paginated list of plans.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        $plans = $this->planService->paginate($request->only([
            'search', 'status', 'visibility', 'interval', 'per_page',
        ]));

        return $this->paginated(PlanResource::collection($plans), 'Plans retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.statistics', title: 'Plan statistics', description: 'Return plan overview statistics.')]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        return $this->success(
            $this->planService->overviewStatistics(),
            'Plan statistics retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'billing.plan.store', title: 'Create plan', description: 'Create a new plan and return it.')]
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create($request->validated());

        return $this->success(new PlanResource($plan), 'Plan created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.plan.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);
        $plan->load(['features.category', 'prices'])->loadCount('features');

        return $this->success(new PlanResource($plan), 'Plan retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.update', title: 'Update plan', description: 'Update an existing plan and return it.')]
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->update($plan, $request->validated());

        return $this->success(new PlanResource($plan), 'Plan updated successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.destroy', title: 'Delete record', description: 'Soft-delete or permanently remove a record.')]
    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);
        $this->planService->delete($plan);

        return $this->success(null, 'Plan deleted successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.bulkDestroy', title: 'Bulk delete plans', description: 'Soft-delete multiple plans by ID.')]
    public function bulkDestroy(BulkDeletePlansRequest $request): JsonResponse
    {
        $count = $this->planService->deleteMany($request->validated('ids'));

        return $this->success(
            ['deleted' => $count],
            "{$count} plan(s) deleted successfully."
        );
    }

    #[Endpoint(operationId: 'billing.plan.bulkActivate', title: 'Bulk activate plans', description: 'Activate multiple plans by ID.')]
    public function bulkActivate(BulkActivatePlansRequest $request): JsonResponse
    {
        $count = $this->planService->activateMany($request->validated('ids'));

        return $this->success(
            ['activated' => $count],
            "{$count} plan(s) activated successfully."
        );
    }

    #[Endpoint(operationId: 'billing.plan.bulkArchive', title: 'Bulk archive plans', description: 'Archive multiple plans by ID.')]
    public function bulkArchive(BulkArchivePlansRequest $request): JsonResponse
    {
        $count = $this->planService->archiveMany($request->validated('ids'));

        return $this->success(
            ['archived' => $count],
            "{$count} plan(s) archived successfully."
        );
    }

    #[Endpoint(operationId: 'billing.plan.restore', title: 'Restore plan', description: 'Restore a soft-deleted plan.')]
    public function restore(Plan $plan): JsonResponse
    {
        $this->authorize('restore', $plan);
        $plan = $this->planService->restore($plan);

        return $this->success(new PlanResource($plan), 'Plan restored successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.activate', title: 'Activate plan', description: 'Activate the plan for normal use.')]
    public function activate(Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);
        $plan = $this->planService->activate($plan);

        return $this->success(new PlanResource($plan), 'Plan activated successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.archive', title: 'Archive plan', description: 'Archive the plan.')]
    public function archive(Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);
        $plan = $this->planService->archive($plan);

        return $this->success(new PlanResource($plan), 'Plan archived successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.syncFeatures', title: 'Sync plan features', description: 'Replace all features assigned to a plan.')]
    public function syncFeatures(SyncPlanFeaturesRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->syncFeatures($plan, $request->validated('features'));

        return $this->success(new PlanResource($plan), 'Plan features synced successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.assignFeature', title: 'Assign feature', description: 'Attach a feature to a plan with limits.')]
    public function assignFeature(AssignPlanFeatureRequest $request, Plan $plan): JsonResponse
    {
        $feature = Feature::query()->findOrFail($request->validated('feature_id'));
        $pivot = $this->planService->assignFeature(
            $plan,
            $feature,
            collect($request->validated())->except('feature_id')->all()
        );

        return $this->success(new PlanFeatureResource($pivot), 'Feature assigned to plan successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.plan.detachFeature', title: 'Detach feature', description: 'Remove a feature from a plan.')]
    public function detachFeature(Plan $plan, Feature $feature): JsonResponse
    {
        $this->authorize('manageFeatures', $plan);
        $this->planService->detachFeature($plan, $feature);

        return $this->success(null, 'Feature detached from plan successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.usageSummary', title: 'Feature usage summary', description: 'Summarize feature usage for a tenant on a plan.')]
    public function usageSummary(Request $request, Plan $plan, Feature $feature, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewUsage', Plan::class);

        return $this->success(
            $this->featureUsageService->summary($tenant, $feature, $plan),
            'Feature usage summary retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.plan.recordUsage', title: 'Record feature usage', description: 'Increment tracked feature usage for a tenant.')]
    public function recordUsage(RecordFeatureUsageRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($request->validated('tenant_id'));
        $feature = Feature::query()->findOrFail($request->validated('feature_id'));
        $plan = $request->validated('plan_id')
            ? Plan::query()->findOrFail($request->validated('plan_id'))
            : null;

        $usage = $this->featureUsageService->record(
            $tenant,
            $feature,
            (int) $request->integer('amount', 1),
            $plan
        );

        return $this->success([
            'id' => $usage->id,
            'tenant_id' => $usage->tenant_id,
            'feature_id' => $usage->feature_id,
            'plan_id' => $usage->plan_id,
            'used' => $usage->used,
            'period_starts_at' => $usage->period_starts_at,
            'period_ends_at' => $usage->period_ends_at,
        ], 'Feature usage recorded successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.plan.prices.index', title: 'List plan prices', description: 'Return all prices for a plan.')]
    public function prices(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return $this->success(
            PlanPriceResource::collection($plan->prices()->orderBy('currency')->get()),
            'Plan prices retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'billing.plan.prices.store', title: 'Create plan price', description: 'Add or upsert a currency price on a plan.')]
    public function storePrice(StorePlanPriceRequest $request, Plan $plan): JsonResponse
    {
        $price = $this->planService->upsertPrice($plan, $request->validated());

        return $this->success(new PlanPriceResource($price), 'Plan price saved successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.plan.prices.update', title: 'Update plan price', description: 'Update an existing plan price.')]
    public function updatePrice(UpdatePlanPriceRequest $request, Plan $plan, PlanPrice $planPrice): JsonResponse
    {
        $data = $request->validated();

        $price = $this->planService->upsertPrice($plan, [
            'id' => $planPrice->id,
            'amount' => $data['amount'] ?? $planPrice->amount,
            'currency' => $data['currency'] ?? $planPrice->currency,
            'billing_interval' => $data['billing_interval'] ?? $planPrice->billing_interval?->value,
            'trial_days' => array_key_exists('trial_days', $data) ? $data['trial_days'] : $planPrice->trial_days,
            'gateway_identifiers' => array_key_exists('gateway_identifiers', $data)
                ? $data['gateway_identifiers']
                : $planPrice->gateway_identifiers,
            'status' => $data['status'] ?? $planPrice->status?->value,
            'metadata' => $data['metadata'] ?? $planPrice->metadata,
        ]);

        return $this->success(new PlanPriceResource($price), 'Plan price updated successfully.');
    }

    #[Endpoint(operationId: 'billing.plan.prices.destroy', title: 'Delete plan price', description: 'Remove a price from a plan.')]
    public function destroyPrice(Plan $plan, PlanPrice $planPrice): JsonResponse
    {
        $this->authorize('update', $plan);
        $this->planService->deletePrice($plan, $planPrice);

        return $this->success(null, 'Plan price deleted successfully.');
    }
}
