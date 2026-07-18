<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Tenants;

use App\Enums\Central\ImpersonationReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenants\BulkActivateTenantsRequest;
use App\Http\Requests\Central\Tenants\BulkDeleteTenantsRequest;
use App\Http\Requests\Central\Tenants\BulkSuspendTenantsRequest;
use App\Http\Requests\Central\Tenants\StartImpersonationRequest;
use App\Http\Requests\Central\Tenants\StoreTenantNoteRequest;
use App\Http\Requests\Central\Tenants\StoreTenantRequest;
use App\Http\Requests\Central\Tenants\SuspendTenantRequest;
use App\Http\Requests\Central\Tenants\SyncTenantTagsRequest;
use App\Http\Requests\Central\Tenants\UpdateTenantMetadataRequest;
use App\Http\Requests\Central\Tenants\UpdateTenantRequest;
use App\Http\Resources\Central\ActivityResource;
use App\Http\Resources\Central\TenantImpersonationResource;
use App\Http\Resources\Central\TenantNoteResource;
use App\Http\Resources\Central\TenantResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantImpersonation;
use App\Models\User;
use App\Services\Central\Tenants\ImpersonationService;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use App\Services\Central\Tenants\TenantService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Tenants', description: 'Tenant lifecycle, notes, health, impersonation.', weight: 90)]
final class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly ImpersonationService $impersonationService,
        private readonly TenantOwnerProvisioningService $ownerProvisioning,
    ) {}

    #[Endpoint(operationId: 'tenants.tenant.index', title: 'List tenants', description: 'Return a paginated list of tenants.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = $this->tenantService->paginate($request->only([
            'search', 'status', 'tag', 'trashed', 'per_page',
        ]));

        return $this->paginated(TenantResource::collection($tenants), 'Tenants retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.options', title: 'Tenant options', description: 'Return all tenant value/label pairs for comboboxes.')]
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        return $this->success(
            $this->tenantService->options($request->string('search')->toString() ?: null),
            'Tenant options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.overviewStatistics', title: 'Tenant overview statistics', description: 'Return aggregate statistics for the tenants index.')]
    public function overviewStatistics(): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        return $this->success(
            $this->tenantService->overviewStatistics(),
            'Tenant statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.store', title: 'Create tenant', description: 'Create a new tenant and return it.')]
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        return $this->success(new TenantResource($tenant), 'Tenant created successfully.', 201);
    }

    #[Endpoint(operationId: 'tenants.tenant.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);
        $tenant->load(['domains'])->loadCount(['domains', 'notes']);

        return $this->success(new TenantResource($tenant), 'Tenant retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.update', title: 'Update tenant', description: 'Update an existing tenant and return it.')]
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->update($tenant, $request->validated());

        return $this->success(new TenantResource($tenant), 'Tenant updated successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.destroy', title: 'Delete record', description: 'Soft-delete or permanently remove a record.')]
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->authorize('delete', $tenant);
        $this->tenantService->delete($tenant);

        return $this->success(null, 'Tenant deleted successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.bulkDestroy', title: 'Bulk delete tenants', description: 'Soft-delete multiple tenants by ID.')]
    public function bulkDestroy(BulkDeleteTenantsRequest $request): JsonResponse
    {
        $count = $this->tenantService->deleteMany($request->validated('ids'));

        return $this->success(
            ['deleted' => $count],
            "{$count} tenant(s) deleted successfully."
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.bulkSuspend', title: 'Bulk suspend tenants', description: 'Suspend multiple tenants by ID.')]
    public function bulkSuspend(BulkSuspendTenantsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $count = $this->tenantService->suspendMany(
            $validated['ids'],
            $validated['reason'] ?? null,
        );

        return $this->success(
            ['suspended' => $count],
            "{$count} tenant(s) suspended successfully."
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.bulkActivate', title: 'Bulk activate tenants', description: 'Activate multiple tenants by ID.')]
    public function bulkActivate(BulkActivateTenantsRequest $request): JsonResponse
    {
        $count = $this->tenantService->activateMany($request->validated('ids'));

        return $this->success(
            ['activated' => $count],
            "{$count} tenant(s) activated successfully."
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.restore', title: 'Restore tenant', description: 'Restore a soft-deleted tenant.')]
    public function restore(Tenant $tenant): JsonResponse
    {
        $this->authorize('restore', $tenant);
        $tenant = $this->tenantService->restore($tenant);

        return $this->success(new TenantResource($tenant), 'Tenant restored successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.suspend', title: 'Suspend tenant', description: 'Suspend a tenant and block platform access.')]
    public function suspend(SuspendTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->suspend($tenant, $request->validated('reason'));

        return $this->success(new TenantResource($tenant), 'Tenant suspended successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.activate', title: 'Activate', description: 'Activate the resource for normal use.')]
    public function activate(Tenant $tenant): JsonResponse
    {
        $this->authorize('activate', $tenant);
        $tenant = $this->tenantService->activate($tenant);

        return $this->success(new TenantResource($tenant), 'Tenant activated successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.archive', title: 'Archive tenant', description: 'Archive the tenant.')]
    public function archive(Tenant $tenant): JsonResponse
    {
        $this->authorize('archive', $tenant);
        $tenant = $this->tenantService->archive($tenant);

        return $this->success(new TenantResource($tenant), 'Tenant archived successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.syncTags', title: 'Sync tags', description: 'Replace tenant tags.')]
    public function syncTags(SyncTenantTagsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->syncTags($tenant, $request->validated('tags'));

        return $this->success(new TenantResource($tenant), 'Tenant tags synced successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.updateMetadata', title: 'Update metadata', description: 'Replace or merge tenant metadata.')]
    public function updateMetadata(UpdateTenantMetadataRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->mergeMetadata($tenant, $request->validated('metadata'));

        return $this->success(new TenantResource($tenant), 'Tenant metadata updated successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.notes', title: 'List notes', description: 'List internal notes for a tenant.')]
    public function notes(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('manageNotes', $tenant);

        $notes = $this->tenantService->paginateNotes($tenant, (int)$request->integer('per_page', 15));

        return $this->paginated(TenantNoteResource::collection($notes), 'Tenant notes retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.storeNote', title: 'Create note', description: 'Add an internal note to a tenant.')]
    public function storeNote(StoreTenantNoteRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $note = $this->tenantService->addNote(
            $tenant,
            $user,
            $request->validated('body'),
            (bool)$request->boolean('is_internal', true)
        );

        return $this->success(new TenantNoteResource($note), 'Tenant note created successfully.', 201);
    }

    #[Endpoint(operationId: 'tenants.tenant.statistics', title: 'tenant statistics', description: 'Return aggregate statistics for tenants.')]
    public function statistics(Tenant $tenant): JsonResponse
    {
        $this->authorize('viewStats', $tenant);

        return $this->success($this->tenantService->statistics($tenant), 'Tenant statistics retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.health', title: 'Health check', description: 'Return health status details.')]
    public function health(Tenant $tenant): JsonResponse
    {
        $this->authorize('viewHealth', $tenant);

        return $this->success($this->tenantService->health($tenant), 'Tenant health retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.activities', title: 'Activity history', description: 'Paginate activity log entries for this subject.')]
    public function activities(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewActivity', $tenant);

        $activities = $this->tenantService->activities($tenant, (int)$request->integer('per_page', 15));

        return $this->paginated(ActivityResource::collection($activities), 'Tenant activities retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenants.tenant.startImpersonation', title: 'Start impersonation', description: 'Create a tenant impersonation session token.')]
    public function startImpersonation(StartImpersonationRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reason = $request->validated('reason');
        $reason = $reason instanceof ImpersonationReason ? $reason : ImpersonationReason::from($reason);

        $result = $this->impersonationService->start(
            $tenant,
            $user,
            $reason,
            $request->validated('reason_notes'),
            $request->ip(),
            $request->userAgent(),
            (int)$request->integer('ttl_minutes', 60),
        );

        return $this->success([
            'impersonation' => new TenantImpersonationResource($result['impersonation']),
            'token' => $result['token'],
            'url' => $result['url'],
        ], 'Impersonation session started.', 201);
    }

    #[Endpoint(operationId: 'tenants.tenant.revokeImpersonation', title: 'Revoke impersonation', description: 'Revoke an active impersonation session.')]
    public function revokeImpersonation(Request $request, Tenant $tenant, TenantImpersonation $impersonation): JsonResponse
    {
        $this->authorize('impersonate', $tenant);

        if ($impersonation->tenant_id !== $tenant->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $impersonation = $this->impersonationService->revoke($impersonation, $user);

        return $this->success(
            new TenantImpersonationResource($impersonation),
            'Impersonation session revoked.'
        );
    }

    #[Endpoint(operationId: 'tenants.tenant.resendOwnerInvite', title: 'Resend owner invite', description: 'Rotate the owner invitation token and resend the welcome email.')]
    public function resendOwnerInvite(Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $result = $this->ownerProvisioning->resendInvite($tenant->fresh(['domains']) ?? $tenant);

        return $this->success([
            'email' => $tenant->email,
            'setup_url' => $result['setup_url'],
            'expires_at' => $tenant->fresh()?->metadata['owner_invite']['expires_at'] ?? null,
        ], 'Owner invitation resent.');
    }
}

