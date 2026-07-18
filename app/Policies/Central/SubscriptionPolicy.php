<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Subscription;
use App\Models\User;

final class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('subscriptions.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->can('subscriptions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('subscriptions.create');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->can('subscriptions.update');
    }

    public function manage(User $user, Subscription $subscription): bool
    {
        return $user->can('subscriptions.manage');
    }
}
