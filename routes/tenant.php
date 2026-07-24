<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Auth\AuthController;
use App\Http\Controllers\Api\Tenant\BrandController;
use App\Http\Controllers\Api\Tenant\EntitlementController;
use App\Http\Controllers\Api\Tenant\SettingController;
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

    Route::get('settings/public', [SettingController::class, 'publicSettings'])
        ->middleware('throttle:api')
        ->name('tenant.settings.public');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('tenant.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('tenant.auth.logout');

        Route::get('entitlements', [EntitlementController::class, 'index'])
            ->name('tenant.entitlements.index');

        Route::get('brands/statistics', [BrandController::class, 'statistics'])
            ->name('tenant.brands.statistics');
        Route::get('brands/options', [BrandController::class, 'options'])
            ->name('tenant.brands.options');
        Route::get('brands/slug/{slug}', [BrandController::class, 'showBySlug'])
            ->name('tenant.brands.show-by-slug');
        Route::post('brands/{brand}/toggle-visibility', [BrandController::class, 'toggleVisibility'])
            ->name('tenant.brands.toggle-visibility');
        Route::post('brands/{brand}/toggle-featured', [BrandController::class, 'toggleFeatured'])
            ->name('tenant.brands.toggle-featured');
        Route::put('brands/reorder', [BrandController::class, 'reorder'])
            ->name('tenant.brands.reorder');
        Route::delete('brands/bulk', [BrandController::class, 'destroyMany'])
            ->name('tenant.brands.destroy-many');
        Route::apiResource('brands', BrandController::class);
    });
});
