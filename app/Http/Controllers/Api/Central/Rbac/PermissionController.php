<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Rbac\BulkDeletePermissionsRequest;
use App\Http\Requests\Central\Rbac\StorePermissionRequest;
use App\Http\Requests\Central\Rbac\UpdatePermissionRequest;
use App\Http\Resources\Central\PermissionResource;
use App\Services\Central\Rbac\PermissionService;
use App\Services\Central\Rbac\RoleService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

#[Group('Central Permissions', description: 'Permission catalog.', weight: 70)]
final class PermissionController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly PermissionService $permissionService,
    ) {}

    #[Endpoint(operationId: 'rbac.permission.index', title: 'List permissions', description: 'Return a paginated flat list of permissions.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = $this->permissionService->paginate($request->only([
            'search', 'group', 'per_page',
        ]));

        return $this->paginated(
            PermissionResource::collection($permissions),
            'Permissions retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'rbac.permission.grouped', title: 'Grouped permissions', description: 'Return permissions grouped by namespace prefix.')]
    public function grouped(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $grouped = $this->roleService->groupedPermissions();

        $data = collect($grouped)->map(function ($permissions, string $group) {
            return [
                'group' => $group,
                'permissions' => PermissionResource::collection($permissions),
            ];
        })->values();

        return $this->success($data, 'Grouped permissions retrieved successfully.');
    }

    #[Endpoint(operationId: 'rbac.permission.matrix', title: 'Permission matrix', description: 'Return roles and grouped permissions for the RBAC matrix UI.')]
    public function matrix(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        return $this->success(
            $this->roleService->permissionMatrix(),
            'Permission matrix retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'rbac.permission.statistics', title: 'Permission statistics', description: 'Return aggregate statistics for the permissions index.')]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        return $this->success(
            $this->permissionService->overviewStatistics(),
            'Permission statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'rbac.permission.store', title: 'Create permission', description: 'Create a new permission and return it.')]
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->create($request->validated());

        return $this->success(new PermissionResource($permission), 'Permission created successfully.', 201);
    }

    #[Endpoint(operationId: 'rbac.permission.show', title: 'Show permission', description: 'Return a single permission by ID.')]
    public function show(Permission $permission): JsonResponse
    {
        $this->authorize('view', $permission);

        return $this->success(new PermissionResource($permission), 'Permission retrieved successfully.');
    }

    #[Endpoint(operationId: 'rbac.permission.update', title: 'Update permission', description: 'Update an existing permission and return it.')]
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->update($permission, $request->validated());

        return $this->success(new PermissionResource($permission), 'Permission updated successfully.');
    }

    #[Endpoint(operationId: 'rbac.permission.destroy', title: 'Delete permission', description: 'Delete a permission.')]
    public function destroy(Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);
        $this->permissionService->delete($permission);

        return $this->success(null, 'Permission deleted successfully.');
    }

    #[Endpoint(operationId: 'rbac.permission.bulkDestroy', title: 'Bulk delete permissions', description: 'Delete multiple permissions by ID.')]
    public function bulkDestroy(BulkDeletePermissionsRequest $request): JsonResponse
    {
        $count = $this->permissionService->deleteMany($request->validated('ids'));

        return $this->success(
            ['deleted' => $count],
            "{$count} permission(s) deleted successfully."
        );
    }
}
