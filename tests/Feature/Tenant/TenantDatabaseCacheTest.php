<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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

it('provisions tenant owner when cache store is database', function (): void {
    config(['cache.default' => 'database']);

    [$tenant] = createProvisionedTenant([
        'email' => 'owner@db-cache.test',
        'domain' => 'db-cache.test',
    ]);

    tenancy()->initialize($tenant);

    try {
        expect(Schema::connection('tenant')->hasTable('cache'))->toBeTrue()
            ->and(Schema::connection('tenant')->hasTable('cache_locks'))->toBeTrue();

        Cache::put('tenant-cache-check', 'ok', 60);

        expect(Cache::get('tenant-cache-check'))->toBe('ok');
    } finally {
        tenancy()->end();
    }
});
