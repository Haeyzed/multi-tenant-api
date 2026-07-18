<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.update');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.delete');
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.restore');
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.suspend');
    }

    public function activate(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.activate');
    }

    public function archive(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.archive');
    }

    public function manageNotes(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.manage-notes');
    }

    public function manageTags(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.manage-tags');
    }

    public function manageMetadata(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.manage-metadata');
    }

    public function viewStats(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view-stats');
    }

    public function viewHealth(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view-health');
    }

    public function viewActivity(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.view-activity');
    }

    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->can('tenants.impersonate');
    }
}
