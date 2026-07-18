<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Support\Central\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Central\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, PermissionCatalog::GUARD);
        }

        $superAdmin = Role::findOrCreate('super-admin', PermissionCatalog::GUARD);
        $superAdmin->syncPermissions(PermissionCatalog::all());

        $operator = Role::findOrCreate('operator', PermissionCatalog::GUARD);
        $operator->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
            'users.view-activity',
            'roles.view',
            'permissions.view',
            'sessions.view',
            'tokens.view',
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.view-stats',
            'tenants.view-health',
            'tenants.view-activity',
            'tenants.manage-notes',
            'domains.view',
            'domains.create',
            'domains.update',
            'domains.verify',
            'features.view',
            'features.create',
            'features.update',
            'features.manage-categories',
            'plans.view',
            'plans.create',
            'plans.update',
            'plans.manage-features',
            'plans.view-usage',
            'subscriptions.view',
            'subscriptions.create',
            'subscriptions.manage',
            'billing.invoices.view',
            'billing.invoices.manage',
            'billing.payments.view',
            'billing.payments.charge',
            'billing.gateways.view',
            'dashboard.view',
            'dashboard.health',
            'settings.view',
            'settings.update',
            'audit.view',
            'notifications.view',
            'notifications.create',
            'notifications.update',
            'notifications.broadcast',
            'notifications.inbox',
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.publish',
            'support.tickets.view',
            'support.tickets.create',
            'support.tickets.update',
            'support.tickets.assign',
            'support.tickets.reply',
            'support.categories.manage',
            'monitoring.view',
            'api.clients.view',
            'api.webhooks.view',
            'ai.view',
            'integrations.view',
            'integrations.manage',
            'themes.view',
            'themes.manage',
            'backups.view',
            'versions.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
