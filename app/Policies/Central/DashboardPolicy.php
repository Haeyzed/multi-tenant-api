<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central dashboard stats, revenue, charts, and health.
 *
 * Registered as named Gate abilities (not a model policy) because dashboard
 * data spans multiple Eloquent models.
 */
final class DashboardPolicy
{
    /**
     * Determine whether the user may view dashboard data.
     */
    public function view(User $user): bool
    {
        return $user->can('dashboard.view');
    }

    /**
     * Determine whether the user may view platform health status.
     */
    public function health(User $user): bool
    {
        return $user->can('dashboard.health');
    }
}
