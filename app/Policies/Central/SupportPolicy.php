<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central support tickets and ticket categories.
 *
 * Registered as named Gate abilities (not a model policy) because support
 * spans multiple Eloquent models (tickets, categories, replies).
 */
final class SupportPolicy
{
    /**
     * Determine whether the user may view support tickets.
     */
    public function viewTickets(User $user): bool
    {
        return $user->can('support.tickets.view');
    }

    /**
     * Determine whether the user may create support tickets.
     */
    public function createTickets(User $user): bool
    {
        return $user->can('support.tickets.create');
    }

    /**
     * Determine whether the user may update support tickets.
     */
    public function updateTickets(User $user): bool
    {
        return $user->can('support.tickets.update');
    }

    /**
     * Determine whether the user may assign support tickets to a staff member.
     */
    public function assignTickets(User $user): bool
    {
        return $user->can('support.tickets.assign');
    }

    /**
     * Determine whether the user may reply to support tickets.
     */
    public function replyTickets(User $user): bool
    {
        return $user->can('support.tickets.reply');
    }

    /**
     * Determine whether the user may delete support tickets.
     */
    public function deleteTickets(User $user): bool
    {
        return $user->can('support.tickets.delete');
    }

    /**
     * Determine whether the user may manage support ticket categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->can('support.categories.manage');
    }
}
