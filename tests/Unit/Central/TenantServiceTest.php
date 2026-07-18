<?php

declare(strict_types=1);

use App\Enums\Central\TenantStatus;
use App\Models\Central\Tenant;
use App\Services\Central\Tenants\TenantService;

it('generates unique slugs when creating tenants', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $service = app(TenantService::class);
    $tenant = $service->create([
        'name' => 'Acme',
        'slug' => 'acme',
    ]);

    expect($tenant->slug)->toBe('acme-1')
        ->and($tenant->status)->toBe(TenantStatus::PENDING);
});

it('computes tenant health based on access and primary domain', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);
    $service = app(TenantService::class);

    expect($service->health($tenant)['healthy'])->toBeFalse();

    $tenant->domains()->create([
        'domain' => 'primary.health.test',
        'is_primary' => true,
        'status' => 'active',
        'type' => 'primary',
    ]);

    expect($service->health($tenant->fresh())['healthy'])->toBeTrue();
});
