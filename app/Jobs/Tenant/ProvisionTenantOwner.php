<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Models\Central\Tenant;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class ProvisionTenantOwner implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(TenantOwnerProvisioningService $provisioning): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->tenant instanceof Tenant
            ? $this->tenant->fresh(['domains'])
            : Tenant::query()->with('domains')->find($this->tenant->getTenantKey());

        if ($tenant === null || ! filled($tenant->email)) {
            return;
        }

        if ($tenant->domains->isEmpty()) {
            return;
        }

        $provisioning->provision($tenant, sendMail: true);
    }
}
