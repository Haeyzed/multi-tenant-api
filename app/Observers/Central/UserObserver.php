<?php

declare(strict_types=1);

namespace App\Observers\Central;

use App\Models\User;

final class UserObserver
{
    public function created(User $user): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('created')
            ->log('User created');
    }

    public function updated(User $user): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('updated')
            ->withProperties([
                'changes' => $user->getChanges(),
            ])
            ->log('User updated');
    }

    public function deleted(User $user): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('deleted')
            ->log('User deleted');
    }

    public function restored(User $user): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('restored')
            ->log('User restored');
    }
}
