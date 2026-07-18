<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function (): void {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id');
    });
});

Route::middleware([
    'api',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->prefix('api/v1')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/setup-password', [AuthController::class, 'setupPassword'])
            ->name('tenant.auth.setup-password');
        Route::post('auth/login', [AuthController::class, 'login'])
            ->name('tenant.auth.login');
        Route::post('auth/impersonate', [AuthController::class, 'impersonate'])
            ->name('tenant.auth.impersonate');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('tenant.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('tenant.auth.logout');
    });
});
