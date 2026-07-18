<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Feature;
use App\Models\User;

final class FeaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('features.view');
    }

    public function view(User $user, Feature $feature): bool
    {
        return $user->can('features.view');
    }

    public function create(User $user): bool
    {
        return $user->can('features.create');
    }

    public function update(User $user, Feature $feature): bool
    {
        return $user->can('features.update');
    }

    public function delete(User $user, Feature $feature): bool
    {
        return $user->can('features.delete');
    }

    public function restore(User $user, Feature $feature): bool
    {
        return $user->can('features.restore');
    }

    public function manageCategories(User $user): bool
    {
        return $user->can('features.manage-categories');
    }
}
