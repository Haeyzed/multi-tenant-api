<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\Setting;

beforeEach(function (): void {
    cleanupTenantDatabases();

    $path = database_path('testing.sqlite');
    if (! file_exists($path)) {
        touch($path);
    }

    $this->artisan('migrate:fresh');
});

afterEach(function (): void {
    cleanupTenantDatabases();
});

it('returns seeded public store settings on the tenant domain', function (): void {
    [, , $domain] = createProvisionedTenant([
        'name' => 'Brightline Retail',
        'email' => 'owner@brightline-settings.test',
        'domain' => 'brightline-settings.test',
    ], withInvite: false);

    tenantJson($domain, 'GET', '/api/v1/settings/public')
        ->assertSuccessful()
        ->assertJsonPath('data.store_name', 'Brightline Retail')
        ->assertJsonPath('data.brand_name', 'Brightline Retail')
        ->assertJsonPath('data.business_name', 'Brightline Retail');

    tenancy()->initialize(
        Tenant::query()->where('email', 'owner@brightline-settings.test')->firstOrFail()
    );

    expect(Setting::query()->where('key', 'store_name')->value('value'))->toBe('Brightline Retail');

    tenancy()->end();
});
