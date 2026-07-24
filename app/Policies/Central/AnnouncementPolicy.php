<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central platform announcements.
 *
 * Registered as named Gate abilities (not a model policy) to align with
 * the World/Billing authorization pattern.
 */
final class AnnouncementPolicy
{
    /**
     * Determine whether the user may view announcements.
     */
    public function view(User $user): bool
    {
        return $user->can('announcements.view');
    }

    /**
     * Determine whether the user may create announcements.
     */
    public function create(User $user): bool
    {
        return $user->can('announcements.create');
    }

    /**
     * Determine whether the user may update announcements.
     */
    public function update(User $user): bool
    {
        return $user->can('announcements.update');
    }

    /**
     * Determine whether the user may publish announcements.
     */
    public function publish(User $user): bool
    {
        return $user->can('announcements.publish');
    }

    /**
     * Determine whether the user may delete announcements.
     */
    public function delete(User $user): bool
    {
        return $user->can('announcements.delete');
    }
}
