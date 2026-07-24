<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central platform operations.
 *
 * Registered as named Gate abilities (not a model policy) because platform
 * ops spans multiple Eloquent models (AI providers, integrations, themes,
 * backups, and platform versions).
 */
final class PlatformPolicy
{
    /**
     * Determine whether the user may view AI provider settings.
     */
    public function viewAi(User $user): bool
    {
        return $user->can('ai.view');
    }

    /**
     * Determine whether the user may manage AI provider settings.
     */
    public function manageAi(User $user): bool
    {
        return $user->can('ai.manage');
    }

    /**
     * Determine whether the user may view marketplace integrations.
     */
    public function viewIntegrations(User $user): bool
    {
        return $user->can('integrations.view');
    }

    /**
     * Determine whether the user may manage marketplace integrations.
     */
    public function manageIntegrations(User $user): bool
    {
        return $user->can('integrations.manage');
    }

    /**
     * Determine whether the user may view theme marketplace entries.
     */
    public function viewThemes(User $user): bool
    {
        return $user->can('themes.view');
    }

    /**
     * Determine whether the user may manage theme marketplace entries.
     */
    public function manageThemes(User $user): bool
    {
        return $user->can('themes.manage');
    }

    /**
     * Determine whether the user may view backup records and schedules.
     */
    public function viewBackups(User $user): bool
    {
        return $user->can('backups.view');
    }

    /**
     * Determine whether the user may manage backup records and schedules.
     */
    public function manageBackups(User $user): bool
    {
        return $user->can('backups.manage');
    }

    /**
     * Determine whether the user may view platform version records.
     */
    public function viewVersions(User $user): bool
    {
        return $user->can('versions.view');
    }

    /**
     * Determine whether the user may manage platform version records.
     */
    public function manageVersions(User $user): bool
    {
        return $user->can('versions.manage');
    }
}
