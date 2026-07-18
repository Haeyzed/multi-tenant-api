<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view') || $actor->is($user);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update');
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.delete') && !$actor->is($user);
    }

    public function restore(User $actor, User $user): bool
    {
        return $actor->can('users.restore');
    }

    public function forceDelete(User $actor, User $user): bool
    {
        return $actor->can('users.delete') && !$actor->is($user);
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $actor->can('users.assign-roles');
    }

    public function assignPermissions(User $actor, User $user): bool
    {
        return $actor->can('users.assign-permissions');
    }

    public function manageStatus(User $actor, User $user): bool
    {
        return $actor->can('users.manage-status');
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $actor->can('users.reset-password');
    }

    public function viewActivity(User $actor, User $user): bool
    {
        return $actor->can('users.view-activity') || $actor->is($user);
    }
}
