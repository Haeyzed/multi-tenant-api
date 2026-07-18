<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\Central\Setting;
use App\Models\User;

final class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->can('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.create');
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->can('settings.update');
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->can('settings.delete');
    }
}
