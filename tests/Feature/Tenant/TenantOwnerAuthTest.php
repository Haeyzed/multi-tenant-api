<?php

declare(strict_types=1);

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Enums\Central\ImpersonationReason;
use App\Enums\Central\TenantStatus;
use App\Mail\Tenant\WelcomeTenantOwner;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Services\Central\Tenants\ImpersonationService;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

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

it('sends a welcome invite when provisioning an owner', function (): void {
    [, $token] = createProvisionedTenant(['email' => 'owner@shop.test', 'domain' => 'shop-invite.test']);

    Mail::assertSent(WelcomeTenantOwner::class);
    expect($token)->not->toBeEmpty();
});

it('lets the owner set a password and login on the tenant domain', function (): void {
    [, $token, $domain] = createProvisionedTenant([
        'email' => 'owner@ready.test',
        'domain' => 'ready.test',
    ]);

    tenantJson($domain, 'POST', '/api/v1/auth/setup-password', [
        'token' => $token,
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['email']]]);

    $login = tenantJson($domain, 'POST', '/api/v1/auth/login', [
        'email' => 'owner@ready.test',
        'password' => 'Password1!',
    ])->assertSuccessful()
        ->assertJsonPath('data.user.email', 'owner@ready.test');

    $bearer = $login->json('data.token');

    tenantJson($domain, 'GET', '/api/v1/auth/me', [], [
        'Authorization' => 'Bearer '.$bearer,
    ])->assertSuccessful()
        ->assertJsonPath('data.email', 'owner@ready.test');

    tenantJson($domain, 'POST', '/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$bearer,
    ])->assertSuccessful();
});

it('rejects tenant login when the tenant is suspended', function (): void {
    [$tenant, , $domain] = createProvisionedTenant([
        'email' => 'owner@paused.test',
        'domain' => 'paused.test',
    ], withInvite: false);

    $tenant->update([
        'status' => TenantStatus::SUSPENDED,
        'suspended_at' => now(),
    ]);

    tenantJson($domain, 'POST', '/api/v1/auth/login', [
        'email' => 'owner@paused.test',
        'password' => 'password',
    ])->assertStatus(422);
});

it('resends the owner invite from central', function (): void {
    [$tenant] = createProvisionedTenant([
        'email' => 'owner@resend.test',
        'domain' => 'resend.test',
    ]);

    Mail::fake();

    actingAsCentralUser(['tenants.update']);

    $this->postJson("/api/v1/tenants/{$tenant->id}/owner/resend-invite")
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    Mail::assertSent(WelcomeTenantOwner::class);
});

it('redeems a central impersonation token on the tenant domain', function (): void {
    [$tenant, , $domain] = createProvisionedTenant([
        'email' => 'owner@impersonate.test',
        'domain' => 'impersonate.test',
        'status' => TenantStatus::ACTIVE,
    ], withInvite: false);

    $actor = actingAsCentralUser(['tenants.impersonate']);

    $result = app(ImpersonationService::class)->start(
        $tenant,
        $actor,
        ImpersonationReason::SUPPORT,
    );

    tenantJson($domain, 'POST', '/api/v1/auth/impersonate', [
        'token' => $result['token'],
    ])->assertSuccessful()
        ->assertJsonPath('data.impersonating', true)
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('creates tenants with email as trial and auto domain', function (): void {
    actingAsCentralUser(['tenants.create', 'tenants.view']);

    config(['app.tenant_base_domain' => 'demo.test']);

    $this->postJson('/api/v1/tenants', [
        'name' => 'Auto Domain Co',
        'email' => 'boss@autodomain.test',
    ])->assertCreated()
        ->assertJsonPath('data.status', TenantStatus::TRIAL->value)
        ->assertJsonPath('data.domains.0.domain', 'auto-domain-co.demo.test');
});

it('invites the owner only after a primary domain exists', function (): void {
    Mail::fake();

    $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
        'email' => 'owner@after-domain.test',
        'status' => TenantStatus::TRIAL,
    ]));

    $provisioning = app(TenantOwnerProvisioningService::class);

    expect(fn () => $provisioning->provision($tenant->fresh(['domains']), sendMail: true))
        ->toThrow(ValidationException::class);

    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'after-domain.test',
        'type' => DomainType::PRIMARY,
        'status' => DomainStatus::ACTIVE,
        'is_primary' => true,
    ]);

    (new CreateDatabase($tenant->fresh()))->handle(app(DatabaseManager::class));
    (new MigrateDatabase($tenant->fresh()))->handle();

    $result = $provisioning->provision($tenant->fresh(['domains']), sendMail: true);

    expect($result['setup_url'])->not->toBeEmpty();
    Mail::assertSent(WelcomeTenantOwner::class);
});
