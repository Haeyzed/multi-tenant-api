<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central API clients and outbound webhooks.
 *
 * Registered as named Gate abilities (not a model policy) because API
 * management spans multiple Eloquent models (clients, webhooks, deliveries).
 */
final class ApiManagementPolicy
{
    /**
     * Determine whether the user may view API clients.
     */
    public function viewClients(User $user): bool
    {
        return $user->can('api.clients.view');
    }

    /**
     * Determine whether the user may manage API clients.
     */
    public function manageClients(User $user): bool
    {
        return $user->can('api.clients.manage');
    }

    /**
     * Determine whether the user may view webhooks.
     */
    public function viewWebhooks(User $user): bool
    {
        return $user->can('api.webhooks.view');
    }

    /**
     * Determine whether the user may manage webhooks.
     */
    public function manageWebhooks(User $user): bool
    {
        return $user->can('api.webhooks.manage');
    }
}
