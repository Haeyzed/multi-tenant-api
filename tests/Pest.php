<?php

declare(strict_types=1);

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Enums\Central\TenantStatus;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use App\Support\Central\PermissionCatalog;
use Database\Seeders\Central\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Central', 'Unit/Central');

pest()->extend(TestCase::class)
    ->in('Feature/Tenant', 'Feature/ExampleTest.php');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * @param  list<string>  $permissions
 * @param  list<string>  $roles
 */
function createCentralUser(array $permissions = [], array $roles = [], array $attributes = []): User
{
    test()->seed(RbacSeeder::class);

    $user = User::factory()->create($attributes);

    if ($roles !== []) {
        $user->syncRoles($roles);
    }

    if ($permissions !== []) {
        $user->syncPermissions($permissions);
    }

    if ($roles === [] && $permissions === []) {
        $user->syncPermissions(PermissionCatalog::all());
    }

    return $user->fresh();
}

function actingAsCentralUser(array $permissions = [], array $roles = [], array $attributes = []): User
{
    $user = createCentralUser($permissions, $roles, $attributes);

    test()->actingAs($user, 'sanctum');

    return $user;
}

function cleanupTenantDatabases(): void
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    foreach (glob(database_path('tenant*')) ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Create a tenant with a real SQLite tenant DB, migrations, and owner invite.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: Tenant, 1: string|null, 2: string} [tenant, plainInviteToken, domain]
 */
function createProvisionedTenant(array $attributes = [], bool $withInvite = true): array
{
    Mail::fake();

    $domain = $attributes['domain'] ?? ('t-'.Str::lower(Str::random(8)).'.test');
    unset($attributes['domain']);

    $email = $attributes['email'] ?? 'owner@'.$domain;

    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function () use ($attributes, $email): Tenant {
        return Tenant::factory()->create(array_merge([
            'status' => TenantStatus::TRIAL,
            'email' => $email,
            'trial_ends_at' => now()->addDays(14),
        ], $attributes));
    });

    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => $domain,
        'type' => DomainType::PRIMARY,
        'status' => DomainStatus::ACTIVE,
        'is_primary' => true,
    ]);

    $tenant = $tenant->fresh(['domains']);

    (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
    (new MigrateDatabase($tenant))->handle();

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $provisioning = app(TenantOwnerProvisioningService::class);

    if ($withInvite) {
        $result = $provisioning->provision($tenant, sendMail: true);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return [$tenant->fresh(['domains']), $result['plain_token'], $domain];
    }

    $provisioning->provisionWithPassword($tenant, 'password');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    return [$tenant->fresh(['domains']), null, $domain];
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function tenantJson(string $domain, string $method, string $uri, array $data = [], array $headers = []): TestResponse
{
    $method = strtoupper($method);
    $url = 'http://'.$domain.$uri;

    return match ($method) {
        'GET' => test()->getJson($url, $headers),
        'POST' => test()->postJson($url, $data, $headers),
        'PUT' => test()->putJson($url, $data, $headers),
        'PATCH' => test()->patchJson($url, $data, $headers),
        'DELETE' => test()->deleteJson($url, $data, $headers),
        default => throw new InvalidArgumentException("Unsupported method [{$method}]"),
    };
}

/**
 * Insert a minimal World country + currency row for billing/signup tests.
 */
function seedWorldCountry(string $iso2, string $name, string $currencyCode): void
{
    if (! \Illuminate\Support\Facades\Schema::hasTable('countries')
        || ! \Illuminate\Support\Facades\Schema::hasTable('currencies')) {
        return;
    }

    $country = \App\Models\World\Country::query()->updateOrCreate(
        ['iso2' => $iso2],
        [
            'name' => $name,
            'status' => 1,
            'phone_code' => '1',
            'iso3' => strtoupper(substr($iso2.'X', 0, 3)),
            'native' => $name,
            'region' => 'Test',
            'subregion' => 'Test',
            'latitude' => '0',
            'longitude' => '0',
            'emoji' => '🏳️',
            'emojiU' => 'U+1F3F3',
        ],
    );

    \App\Models\World\Currency::query()->updateOrCreate(
        ['country_id' => $country->id],
        [
            'name' => $currencyCode.' Currency',
            'code' => $currencyCode,
            'precision' => 2,
            'symbol' => $currencyCode,
            'symbol_native' => $currencyCode,
            'symbol_first' => true,
            'decimal_mark' => '.',
            'thousands_separator' => ',',
        ],
    );
}

