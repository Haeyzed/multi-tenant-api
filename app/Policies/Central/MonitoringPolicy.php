<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central queue, jobs, database, storage, and server monitoring.
 *
 * Registered as named Gate abilities (not a model policy) because monitoring
 * spans multiple infrastructure concerns rather than a single Eloquent model.
 */
final class MonitoringPolicy
{
    /**
     * Determine whether the user may view monitoring data.
     */
    public function view(User $user): bool
    {
        return $user->can('monitoring.view');
    }

    /**
     * Determine whether the user may manage monitoring (retry/flush failed jobs).
     */
    public function manage(User $user): bool
    {
        return $user->can('monitoring.manage');
    }
}
