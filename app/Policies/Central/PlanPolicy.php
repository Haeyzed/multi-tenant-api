<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Plan;
use App\Models\User;

final class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plans.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->can('plans.view');
    }

    public function create(User $user): bool
    {
        return $user->can('plans.create');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can('plans.update');
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->can('plans.delete');
    }

    public function restore(User $user, Plan $plan): bool
    {
        return $user->can('plans.restore');
    }

    public function manageFeatures(User $user, Plan $plan): bool
    {
        return $user->can('plans.manage-features');
    }

    public function viewUsage(User $user): bool
    {
        return $user->can('plans.view-usage');
    }

    public function recordUsage(User $user): bool
    {
        return $user->can('plans.record-usage');
    }
}
