<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central world geography and locale reference data.
 *
 * Registered as named Gate abilities (not a model policy) because world admin
 * spans multiple Eloquent models (countries, states, cities, currencies, timezones, languages).
 */
final class WorldPolicy
{
    /**
     * Determine whether the user may view world reference data.
     */
    public function view(User $user): bool
    {
        return $user->can('world.view');
    }

    /**
     * Determine whether the user may create world reference data.
     */
    public function create(User $user): bool
    {
        return $user->can('world.create');
    }

    /**
     * Determine whether the user may update world reference data.
     */
    public function update(User $user): bool
    {
        return $user->can('world.update');
    }

    /**
     * Determine whether the user may delete world reference data.
     */
    public function delete(User $user): bool
    {
        return $user->can('world.delete');
    }
}
