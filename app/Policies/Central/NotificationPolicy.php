<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central platform notifications and inbox delivery.
 *
 * Registered as named Gate abilities (not a model policy) because notifications
 * span multiple Eloquent models (platform notifications and deliveries).
 */
final class NotificationPolicy
{
    /**
     * Determine whether the user may view notifications.
     */
    public function view(User $user): bool
    {
        return $user->can('notifications.view');
    }

    /**
     * Determine whether the user may create notifications.
     */
    public function create(User $user): bool
    {
        return $user->can('notifications.create');
    }

    /**
     * Determine whether the user may update notifications.
     */
    public function update(User $user): bool
    {
        return $user->can('notifications.update');
    }

    /**
     * Determine whether the user may delete notifications.
     */
    public function delete(User $user): bool
    {
        return $user->can('notifications.delete');
    }

    /**
     * Determine whether the user may broadcast notifications.
     */
    public function broadcast(User $user): bool
    {
        return $user->can('notifications.broadcast');
    }

    /**
     * Determine whether the user may access the notification inbox.
     */
    public function inbox(User $user): bool
    {
        return $user->can('notifications.inbox');
    }
}
