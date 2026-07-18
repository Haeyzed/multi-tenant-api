<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Rbac\BulkDeleteRolesRequest;
use App\Http\Requests\Central\Rbac\StoreRoleRequest;
use App\Http\Requests\Central\Rbac\SyncRolePermissionsRequest;
use App\Http\Requests\Central\Rbac\UpdateRoleRequest;
use App\Http\Resources\Central\RoleResource;
use App\Models\Central\Role;
use App\Services\Central\Rbac\RoleService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Roles', description: 'Role CRUD and permission sync.', weight: 60)]
final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    )
    {
    }

    #[Endpoint(operationId: 'rbac.role.index', title: 'List roles', description: 'Return a paginated list of roles.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->roleService->paginate($request->only(['search', 'per_page']));

        return $this->paginated(RoleResource::collection($roles), 'Roles retrieved successfully.');
    }

    #[Endpoint(operationId: 'rbac.role.statistics', title: 'Role statistics', description: 'Return aggregate statistics for the roles index.')]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return $this->success(
            $this->roleService->overviewStatistics(),
            'Role statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'rbac.role.store', title: 'Create role', description: 'Create a new role and return it.')]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return $this->success(new RoleResource($role), 'Role created successfully.', 201);
    }

    #[Endpoint(operationId: 'rbac.role.show', title: 'Show record', description: 'Return a single record by ID.')]
    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->load('permissions')->loadCount('users');

        return $this->success(new RoleResource($role), 'Role retrieved successfully.');
    }

    #[Endpoint(operationId: 'rbac.role.update', title: 'Update role', description: 'Update an existing role and return it.')]
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return $this->success(new RoleResource($role), 'Role updated successfully.');
    }

    #[Endpoint(operationId: 'rbac.role.destroy', title: 'Delete record', description: 'Soft-delete or permanently remove a record.')]
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);
        $this->roleService->delete($role);

        return $this->success(null, 'Role deleted successfully.');
    }

    #[Endpoint(operationId: 'rbac.role.bulkDestroy', title: 'Bulk delete roles', description: 'Delete multiple roles by ID.')]
    public function bulkDestroy(BulkDeleteRolesRequest $request): JsonResponse
    {
        $count = $this->roleService->deleteMany($request->validated('ids'));

        return $this->success(
            ['deleted' => $count],
            "{$count} role(s) deleted successfully."
        );
    }

    #[Endpoint(operationId: 'rbac.role.syncPermissions', title: 'Sync permissions', description: 'Replace assigned permissions for the role or user.')]
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->syncPermissions($role, $request->validated('permissions'));

        return $this->success(new RoleResource($role), 'Role permissions synced successfully.');
    }
}

