<?php

declare(strict_types=1);

namespace App\Providers;

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
use App\Policies\Central\DomainPolicy;
use App\Policies\Central\FeaturePolicy;
use App\Policies\Central\PermissionPolicy;
use App\Policies\Central\PlanPolicy;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\SettingPolicy;
use App\Policies\Central\SubscriptionPolicy;
use App\Policies\Central\TenantPolicy;
use App\Policies\Central\UserPolicy;
use App\Services\Central\Settings\ApplySettingsToConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
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

        DatabaseConfig::generateDatabaseNamesUsing(function ($tenant) {
            return config('tenancy.database.prefix', 'tenant-')
                . $tenant->slug
                . config('tenancy.database.suffix', '');
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
    }
}
