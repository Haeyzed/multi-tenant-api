<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Models\Central\SignupIntent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $centralDomains = array_values(array_unique(array_filter([
                is_string($appHost) && $appHost !== '' ? $appHost : null,
                ...config('tenancy.central_domains', ['localhost', '127.0.0.1']),
            ])));

            foreach ($centralDomains as $domain) {
                Route::domain($domain)
                    ->middleware('api')
                    ->prefix('api/v1')
                    ->group(base_path('routes/api/central.php'));
            }
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('billing:process-trials')->daily();
        $schedule->command('model:prune', [
            '--model' => [SignupIntent::class],
        ])->hourly()->withoutOverlapping()->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Allow optional client-only query params on signed billing checkout links.
        ValidateSignature::except(['format']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*'),
        );

        $exceptions->render(new ApiExceptionRenderer);
    })->create();
