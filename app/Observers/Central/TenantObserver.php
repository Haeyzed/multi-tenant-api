<?php

declare(strict_types=1);

namespace App\Observers\Central;

use App\Models\Central\Tenant;

final class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id])
            ->event('created')
            ->log('Tenant created');
    }

    public function updated(Tenant $tenant): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'changes' => $tenant->getChanges(),
            ])
            ->event('updated')
            ->log('Tenant updated');
    }

    public function deleted(Tenant $tenant): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id])
            ->event('deleted')
            ->log('Tenant deleted');
    }

    public function restored(Tenant $tenant): void
    {
        activity()
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id])
            ->event('restored')
            ->log('Tenant restored');
    }
}
