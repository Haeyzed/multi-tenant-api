<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Models\Central\Tenant;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

#[Signature('tenants:provision-owner {tenant? : Tenant ID or slug} {--password= : Set a known password instead of emailing an invite} {--migrate : Create DB and run tenant migrations first} {--all : Provision every tenant with an email}')]
#[Description('Provision or re-invite the tenant owner user')]
class ProvisionTenantOwnerCommand extends Command
{
    public function handle(TenantOwnerProvisioningService $provisioning): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            if ($this->option('migrate')) {
                $this->info("Migrating tenant {$tenant->slug}…");
                (new CreateDatabase($tenant))->handle(app(\Stancl\Tenancy\Database\DatabaseManager::class));
                (new MigrateDatabase($tenant))->handle();
            }

            if (! filled($tenant->email)) {
                $this->warn("Skipping {$tenant->slug}: no email.");

                continue;
            }

            $tenant->load('domains');

            if ($tenant->domains->isEmpty()) {
                $this->warn("Skipping {$tenant->slug}: no domains.");

                continue;
            }

            $password = $this->option('password');

            if (is_string($password) && $password !== '') {
                $provisioning->provisionWithPassword($tenant, $password);
                $this->info("Owner ready for {$tenant->slug} ({$tenant->email}) with password.");
            } else {
                $result = $provisioning->provision($tenant, sendMail: true);
                $this->info("Invite sent for {$tenant->slug} ({$tenant->email})");
                $this->line('Setup URL: '.$result['setup_url']);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function resolveTenants(): \Illuminate\Support\Collection
    {
        if ($this->option('all')) {
            return Tenant::query()->whereNotNull('email')->get();
        }

        $key = $this->argument('tenant');

        if (! is_string($key) || $key === '') {
            $this->error('Provide a tenant ID/slug or use --all.');

            return collect();
        }

        $tenant = Tenant::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->first();

        return $tenant ? collect([$tenant]) : collect();
    }
}
