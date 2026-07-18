<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\ActivityResource;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Central\Audit\AuditService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Central Audit', description: 'Activity log search and export.', weight: 170)]
final class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    )
    {
    }

    #[Endpoint(operationId: 'audit.index', title: 'List audits', description: 'Return a paginated list of audits.')]
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $activities = $this->auditService->paginate($request->only([
            'search', 'log_name', 'event', 'causer_id', 'subject_type', 'subject_id', 'from', 'to', 'per_page',
        ]));

        return $this->paginated(
            ActivityResource::collection($activities),
            'Audit logs retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'audit.show', title: 'Show audit', description: 'Return a single audit by ID.')]
    public function show(Request $request, int $activity): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $record = $this->auditService->find($activity);

        return $this->success(new ActivityResource($record), 'Audit log retrieved successfully.');
    }

    #[Endpoint(operationId: 'audit.userActivities', title: 'User audit logs', description: 'Paginate activities caused by or about a user.')]
    public function userActivities(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $activities = $this->auditService->userActivities($user, $request->only([
            'search', 'event', 'from', 'to', 'per_page',
        ]));

        return $this->paginated(
            ActivityResource::collection($activities),
            'User audit logs retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'audit.tenantActivities', title: 'Tenant audit logs', description: 'Paginate activities performed on a tenant.')]
    public function tenantActivities(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $activities = $this->auditService->tenantActivities($tenant, $request->only([
            'search', 'event', 'from', 'to', 'per_page',
        ]));

        return $this->paginated(
            ActivityResource::collection($activities),
            'Tenant audit logs retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'audit.export', title: 'Export audit logs', description: 'Stream matching audit logs as CSV.')]
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('audit.export'), 403);

        return $this->auditService->export($request->only([
            'search', 'log_name', 'event', 'causer_id', 'subject_type', 'subject_id', 'from', 'to',
        ]));
    }
}

