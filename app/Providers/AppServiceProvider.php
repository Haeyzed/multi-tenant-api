<?php

declare(strict_types=1);

namespace App\Providers;

use App\Facades\Payment as PaymentFacade;
use App\Models\Central\Domain;
use App\Models\Central\Feature;
use App\Models\Central\Plan;
use App\Models\Central\Role;
use App\Models\Central\Setting;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Observers\Central\TenantObserver;
use App\Observers\Central\UserObserver;
use App\Payments\PaymentGatewayManager;
use App\Policies\Central\AnnouncementPolicy;
use App\Policies\Central\ApiManagementPolicy;
use App\Policies\Central\AuditPolicy;
use App\Policies\Central\BillingPolicy;
use App\Policies\Central\DashboardPolicy;
use App\Policies\Central\DomainPolicy;
use App\Policies\Central\FeaturePolicy;
use App\Policies\Central\MonitoringPolicy;
use App\Policies\Central\NotificationPolicy;
use App\Policies\Central\PermissionPolicy;
use App\Policies\Central\PlanPolicy;
use App\Policies\Central\PlatformPolicy;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\SettingPolicy;
use App\Policies\Central\SubscriptionPolicy;
use App\Policies\Central\SupportPolicy;
use App\Policies\Central\TenantPolicy;
use App\Policies\Central\UserPolicy;
use App\Policies\Central\WorldPolicy;
use App\Services\Central\Settings\ApplySettingsToConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Stancl\Tenancy\DatabaseConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        AliasLoader::getInstance()->alias('Payment', PaymentFacade::class);

        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant) {
            return config('tenancy.database.prefix', 'tenant-')
                .$tenant->slug
                .config('tenancy.database.suffix', '');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureScramble();
        $this->configureAuthorization();
        $this->applyRuntimeSettings();

        User::observe(UserObserver::class);
        Tenant::observe(TenantObserver::class);
    }

    private function applyRuntimeSettings(): void
    {
        try {
            app(ApplySettingsToConfig::class)->apply();
        } catch (\Throwable) {
            // Settings table or cache may be unavailable during install/migrate.
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }

    private function configureScramble(): void
    {
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            return app()->environment(['local', 'testing'])
                || ($user?->can('docs.view') ?? false);
        });

        // Default API = Central landlord docs at /docs/central
        Scramble::configure()
            ->routes(fn (Route $route): bool => $this->isApiControllerRoute($route, 'Central'))
            ->expose(
                ui: '/docs/central',
                document: '/docs/central.json',
            );

        // Tenant-domain API docs at /docs/tenant
        Scramble::registerApi('tenant', [
            'api_path' => 'api/v1',
            'export_path' => 'docs/openapi-tenant.json',
            'cache' => [
                'key' => 'scramble.openapi.tenant',
                'store' => 'file',
            ],
            'info' => [
                'version' => env('API_VERSION', '1.0.0'),
                'description' => <<<'MD'
# Tenant API

REST API for a **tenant** application (served on the tenant domain).

## Base URL

Endpoints are relative to `/api/v1` on the tenant hostname
(for example `https://acme.localhost/api/v1`).

## Authentication

- Use **Laravel Sanctum** bearer tokens (`Authorization: Bearer {token}`).
- Owners set a password via `POST /auth/setup-password`, then `POST /auth/login`.
- Impersonation redeem: `POST /auth/impersonate`.

## Response envelope

Same JSON envelope as the Central API: `{ status, message, data, meta, errors }`.
MD,
            ],
            'ui' => [
                'title' => 'Tenant API',
            ],
        ])
            ->routes(fn (Route $route): bool => $this->isApiControllerRoute($route, 'Tenant'))
            ->expose(
                ui: '/docs/tenant',
                document: '/docs/tenant.json',
            );
    }

    /**
     * Determine whether a route belongs to the given API controller namespace.
     */
    private function isApiControllerRoute(Route $route, string $segment): bool
    {
        $controller = $route->getControllerClass();

        if (! is_string($controller) || $controller === '') {
            return false;
        }

        return str_starts_with($controller, 'App\\Http\\Controllers\\Api\\'.$segment.'\\');
    }

    private function configureAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Feature::class, FeaturePolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);

        Gate::define('viewBillingInvoices', [BillingPolicy::class, 'viewInvoices']);
        Gate::define('manageBillingInvoices', [BillingPolicy::class, 'manageInvoices']);
        Gate::define('viewBillingPayments', [BillingPolicy::class, 'viewPayments']);
        Gate::define('chargeBillingPayments', [BillingPolicy::class, 'charge']);
        Gate::define('refundBillingPayments', [BillingPolicy::class, 'refund']);
        Gate::define('manageBillingAddresses', [BillingPolicy::class, 'manageAddresses']);
        Gate::define('viewBillingGateways', [BillingPolicy::class, 'viewGateways']);
        Gate::define('viewBillingProfile', [BillingPolicy::class, 'viewBillingProfile']);
        Gate::define('viewBillingGatewayOptions', [BillingPolicy::class, 'viewGatewayOptions']);

        Gate::define('viewWorld', [WorldPolicy::class, 'view']);
        Gate::define('createWorld', [WorldPolicy::class, 'create']);
        Gate::define('updateWorld', [WorldPolicy::class, 'update']);
        Gate::define('deleteWorld', [WorldPolicy::class, 'delete']);

        Gate::define('viewSupportTickets', [SupportPolicy::class, 'viewTickets']);
        Gate::define('createSupportTickets', [SupportPolicy::class, 'createTickets']);
        Gate::define('updateSupportTickets', [SupportPolicy::class, 'updateTickets']);
        Gate::define('assignSupportTickets', [SupportPolicy::class, 'assignTickets']);
        Gate::define('replySupportTickets', [SupportPolicy::class, 'replyTickets']);
        Gate::define('deleteSupportTickets', [SupportPolicy::class, 'deleteTickets']);
        Gate::define('manageSupportCategories', [SupportPolicy::class, 'manageCategories']);

        Gate::define('viewAi', [PlatformPolicy::class, 'viewAi']);
        Gate::define('manageAi', [PlatformPolicy::class, 'manageAi']);
        Gate::define('viewIntegrations', [PlatformPolicy::class, 'viewIntegrations']);
        Gate::define('manageIntegrations', [PlatformPolicy::class, 'manageIntegrations']);
        Gate::define('viewThemes', [PlatformPolicy::class, 'viewThemes']);
        Gate::define('manageThemes', [PlatformPolicy::class, 'manageThemes']);
        Gate::define('viewBackups', [PlatformPolicy::class, 'viewBackups']);
        Gate::define('manageBackups', [PlatformPolicy::class, 'manageBackups']);
        Gate::define('viewVersions', [PlatformPolicy::class, 'viewVersions']);
        Gate::define('manageVersions', [PlatformPolicy::class, 'manageVersions']);

        Gate::define('viewAudit', [AuditPolicy::class, 'view']);
        Gate::define('exportAudit', [AuditPolicy::class, 'export']);

        Gate::define('viewDashboard', [DashboardPolicy::class, 'view']);
        Gate::define('viewDashboardHealth', [DashboardPolicy::class, 'health']);

        Gate::define('viewMonitoring', [MonitoringPolicy::class, 'view']);
        Gate::define('manageMonitoring', [MonitoringPolicy::class, 'manage']);

        Gate::define('viewAnnouncements', [AnnouncementPolicy::class, 'view']);
        Gate::define('createAnnouncements', [AnnouncementPolicy::class, 'create']);
        Gate::define('updateAnnouncements', [AnnouncementPolicy::class, 'update']);
        Gate::define('publishAnnouncements', [AnnouncementPolicy::class, 'publish']);
        Gate::define('deleteAnnouncements', [AnnouncementPolicy::class, 'delete']);

        Gate::define('viewNotifications', [NotificationPolicy::class, 'view']);
        Gate::define('createNotifications', [NotificationPolicy::class, 'create']);
        Gate::define('updateNotifications', [NotificationPolicy::class, 'update']);
        Gate::define('deleteNotifications', [NotificationPolicy::class, 'delete']);
        Gate::define('broadcastNotifications', [NotificationPolicy::class, 'broadcast']);
        Gate::define('inboxNotifications', [NotificationPolicy::class, 'inbox']);

        Gate::define('viewApiClients', [ApiManagementPolicy::class, 'viewClients']);
        Gate::define('manageApiClients', [ApiManagementPolicy::class, 'manageClients']);
        Gate::define('viewApiWebhooks', [ApiManagementPolicy::class, 'viewWebhooks']);
        Gate::define('manageApiWebhooks', [ApiManagementPolicy::class, 'manageWebhooks']);
    }
}
