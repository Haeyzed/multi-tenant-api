<?php

declare(strict_types=1);

namespace App\Services\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Service responsible for central permission catalog CRUD.
 */
final class PermissionService
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    )
    {
    }

    /**
     * Paginate permissions with optional search and group filters.
     *
     * @param array{search?: string, group?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Permission>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Permission::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->when(
                $filters['group'] ?? null,
                fn($query, string $group) => $query->where('name', 'like', "{$group}.%")
            )
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a permission for the central guard.
     *
     * @param array{name: string} $data
     */
    public function create(array $data): Permission
    {
        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => PermissionCatalog::GUARD,
        ]);

        $this->permissionRegistrar->forgetCachedPermissions();

        return $permission;
    }

    /**
     * Update a permission name.
     *
     * @param array{name?: string} $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        if (isset($data['name'])) {
            $permission->name = $data['name'];
            $permission->save();
            $this->permissionRegistrar->forgetCachedPermissions();
        }

        return $permission->fresh();
    }

    /**
     * Soft-delete is not used for permissions; permanently delete many.
     *
     * @param list<int> $ids
     */
    public function deleteMany(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $permissions = Permission::query()
                ->where('guard_name', PermissionCatalog::GUARD)
                ->whereIn('id', $ids)
                ->get();

            $permissions->each(fn(Permission $permission) => $this->delete($permission));

            return $permissions->count();
        });
    }

    /**
     * Delete a permission and clear the cache.
     */
    public function delete(Permission $permission): void
    {
        $permission->delete();
        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * Aggregate overview statistics for the permissions index.
     *
     * @return array{total: int, groups: int, catalog_total: int}
     */
    public function overviewStatistics(): array
    {
        $permissions = Permission::query()
            ->where('guard_name', PermissionCatalog::GUARD)
            ->pluck('name');

        $groups = $permissions
            ->map(fn(string $name): string => explode('.', $name)[0] ?? 'other')
            ->unique()
            ->count();

        return [
            'total' => $permissions->count(),
            'groups' => $groups,
            'catalog_total' => count(PermissionCatalog::all()),
        ];
    }
}
