<?php

declare(strict_types=1);

namespace App\Services\Central\Rbac;

use App\Models\Central\Role;
use App\Support\Central\PermissionCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Service responsible for central role and permission management.
 *
 * Encapsulates role CRUD, permission synchronization, and grouped permission
 * catalog queries so controllers remain thin.
 */
final class RoleService
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    )
    {
    }

    /**
     * Paginate central roles with optional search filter.
     *
     * @param array{search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->where('guard_name', PermissionCatalog::GUARD)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new central role with optional permissions.
     *
     * @param array{name: string, permissions?: list<string>} $data
     * @return Role
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => PermissionCatalog::GUARD,
            ]);

            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            $this->permissionRegistrar->forgetCachedPermissions();

            return $role->load('permissions');
        });
    }

    /**
     * Synchronize permissions assigned to a role.
     *
     * @param Role $role
     * @param list<string> $permissions
     * @return Role
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        $this->permissionRegistrar->forgetCachedPermissions();

        return $role->load('permissions');
    }

    /**
     * Update a role's name and/or permissions.
     *
     * @param Role $role
     * @param array{name?: string, permissions?: list<string>} $data
     * @return Role
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            if (isset($data['name'])) {
                $role->name = $data['name'];
                $role->save();
            }

            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions($data['permissions'] ?? []);
            }

            $this->permissionRegistrar->forgetCachedPermissions();

            return $role->load('permissions');
        });
    }

    /**
     * Delete multiple roles, skipping protected roles.
     *
     * @param list<int> $ids
     * @return int
     */
    public function deleteMany(array $ids): int
    {
        $roles = Role::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->whereIn('id', $ids)
            ->where('name', '!=', 'super-admin')
            ->get();

        $roles->each(fn(Role $role) => $this->delete($role));

        return $roles->count();
    }

    /**
     * Delete a role and clear the permission cache.
     *
     * @param Role $role
     * @return void
     *
     * @throws HttpException
     */
    public function delete(Role $role): void
    {
        if ($role->name === 'super-admin') {
            abort(422, 'The super-admin role cannot be deleted.');
        }

        $role->delete();
        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * Aggregate overview statistics for the roles index page.
     *
     * @return array{
     *     total_roles: int,
     *     total_permissions: int,
     *     assigned_users: int,
     *     groups: int
     * }
     */
    public function overviewStatistics(): array
    {
        $roles = Role::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->withCount('users')
            ->get();

        return [
            'total_roles' => $roles->count(),
            'total_permissions' => Permission::query()
                ->where('guard_name', PermissionCatalog::GUARD)
                ->count(),
            'assigned_users' => (int)$roles->sum('users_count'),
            'groups' => count($this->groupedPermissions()),
        ];
    }

    /**
     * Retrieve all permissions grouped by their namespace prefix.
     *
     * @return array<string, Collection<int, Permission>>
     */
    public function groupedPermissions(): array
    {
        $permissions = Permission::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->orderBy('name')
            ->get()
            ->groupBy(fn(Permission $permission): string => explode('.', $permission->name)[0] ?? 'other');

        return $permissions->all();
    }

    /**
     * Build a role × permission matrix payload for the RBAC UI.
     *
     * @return array{
     *     groups: list<array{group: string, permissions: list<array{id: int, name: string, guard_name: string}>}>,
     *     roles: list<array{id: int, name: string, permissions: list<string>, users_count: int}>,
     *     matrix: array<string, list<string>>
     * }
     */
    public function permissionMatrix(): array
    {
        $grouped = $this->groupedPermissions();

        $groups = collect($grouped)->map(function (Collection $permissions, string $group): array {
            return [
                'group' => $group,
                'permissions' => $permissions->map(fn(Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])->values()->all(),
            ];
        })->values()->all();

        $roles = Role::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $rolePayload = $roles->map(fn(Role $role): array => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
            'users_count' => (int)$role->users_count,
        ])->values()->all();

        $matrix = [];
        foreach ($rolePayload as $role) {
            $matrix[(string)$role['id']] = $role['permissions'];
        }

        return [
            'groups' => $groups,
            'roles' => $rolePayload,
            'matrix' => $matrix,
        ];
    }
}
