<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Domain;
use App\Models\User;

final class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('domains.view');
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->can('domains.view');
    }

    public function create(User $user): bool
    {
        return $user->can('domains.create');
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->can('domains.update');
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $user->can('domains.delete');
    }

    public function verify(User $user, Domain $domain): bool
    {
        return $user->can('domains.verify');
    }

    public function manageSsl(User $user, Domain $domain): bool
    {
        return $user->can('domains.manage-ssl');
    }

    public function managePrimary(User $user, Domain $domain): bool
    {
        return $user->can('domains.manage-primary');
    }
}
