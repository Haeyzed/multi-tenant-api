<?php

declare(strict_types=1);

namespace App\Support\Central;

final class PermissionCatalog
{
    public const GUARD = 'web';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::grouped()))));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'users' => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.restore',
                'users.assign-roles',
                'users.assign-permissions',
                'users.manage-status',
                'users.reset-password',
                'users.view-activity',
            ],
            'roles' => [
                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',
                'roles.assign-permissions',
            ],
            'permissions' => [
                'permissions.view',
                'permissions.create',
                'permissions.update',
                'permissions.delete',
            ],
            'sessions' => [
                'sessions.view',
                'sessions.revoke',
            ],
            'tokens' => [
                'tokens.view',
                'tokens.create',
                'tokens.revoke',
            ],
            'tenants' => [
                'tenants.view',
                'tenants.create',
                'tenants.update',
                'tenants.delete',
                'tenants.restore',
                'tenants.suspend',
                'tenants.activate',
                'tenants.archive',
                'tenants.manage-notes',
                'tenants.manage-tags',
                'tenants.manage-metadata',
                'tenants.view-stats',
                'tenants.view-health',
                'tenants.view-activity',
                'tenants.impersonate',
            ],
            'domains' => [
                'domains.view',
                'domains.create',
                'domains.update',
                'domains.delete',
                'domains.verify',
                'domains.manage-ssl',
                'domains.manage-primary',
            ],
            'features' => [
                'features.view',
                'features.create',
                'features.update',
                'features.delete',
                'features.restore',
                'features.manage-categories',
            ],
            'plans' => [
                'plans.view',
                'plans.create',
                'plans.update',
                'plans.delete',
                'plans.restore',
                'plans.manage-features',
                'plans.view-usage',
                'plans.record-usage',
            ],
            'subscriptions' => [
                'subscriptions.view',
                'subscriptions.create',
                'subscriptions.update',
                'subscriptions.manage',
            ],
            'billing' => [
                'billing.invoices.view',
                'billing.invoices.manage',
                'billing.payments.view',
                'billing.payments.charge',
                'billing.payments.refund',
                'billing.addresses.manage',
                'billing.gateways.view',
            ],
            'dashboard' => [
                'dashboard.view',
                'dashboard.health',
            ],
            'settings' => [
                'settings.view',
                'settings.create',
                'settings.update',
                'settings.delete',
            ],
            'audit' => [
                'audit.view',
                'audit.export',
            ],
            'notifications' => [
                'notifications.view',
                'notifications.create',
                'notifications.update',
                'notifications.delete',
                'notifications.broadcast',
                'notifications.inbox',
            ],
            'announcements' => [
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'announcements.delete',
                'announcements.publish',
            ],
            'support' => [
                'support.tickets.view',
                'support.tickets.create',
                'support.tickets.update',
                'support.tickets.delete',
                'support.tickets.assign',
                'support.tickets.reply',
                'support.categories.manage',
            ],
            'monitoring' => [
                'monitoring.view',
                'monitoring.manage',
            ],
            'world' => [
                'world.view',
                'world.create',
                'world.update',
                'world.delete',
            ],
            'api' => [
                'api.clients.view',
                'api.clients.manage',
                'api.webhooks.view',
                'api.webhooks.manage',
            ],
            'ai' => [
                'ai.view',
                'ai.manage',
            ],
            'integrations' => [
                'integrations.view',
                'integrations.manage',
            ],
            'themes' => [
                'themes.view',
                'themes.manage',
            ],
            'backups' => [
                'backups.view',
                'backups.manage',
            ],
            'versions' => [
                'versions.view',
                'versions.manage',
            ],
        ];
    }
}
