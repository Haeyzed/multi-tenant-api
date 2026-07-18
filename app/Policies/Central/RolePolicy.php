<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Role;
use App\Models\User;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete');
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->can('roles.assign-permissions');
    }
}
