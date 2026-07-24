<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central activity log search and export.
 *
 * Registered as named Gate abilities (not a model policy) because audit
 * activity spans multiple Eloquent models.
 */
final class AuditPolicy
{
    /**
     * Determine whether the user may view audit logs.
     */
    public function view(User $user): bool
    {
        return $user->can('audit.view');
    }

    /**
     * Determine whether the user may export audit logs.
     */
    public function export(User $user): bool
    {
        return $user->can('audit.export');
    }
}
