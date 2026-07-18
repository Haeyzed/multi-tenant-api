<?php

declare(strict_types=1);

use App\Enums\Central\ImpersonationReason;
use App\Enums\Central\TenantStatus;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\TenantImpersonation;
use App\Services\Central\Settings\SettingService;
use Database\Seeders\Central\SettingSeeder;

it('lists tenants for authorized users', function (): void {
    actingAsCentralUser(['tenants.view']);
    Tenant::factory()->count(2)->create();

    $this->getJson('/api/v1/tenants')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['meta' => ['total']]);

    $options = $this->getJson('/api/v1/tenants/options')
        ->assertSuccessful();

    expect($options->json('data'))->toHaveCount(Tenant::query()->count());
    expect($options->json('data.0'))->toHaveKeys(['value', 'label']);
});

it('returns tenant overview statistics', function (): void {
    actingAsCentralUser(['tenants.view']);

    Tenant::factory()->count(2)->create(['status' => TenantStatus::ACTIVE]);
    Tenant::factory()->create(['status' => TenantStatus::SUSPENDED]);

    $this->getJson('/api/v1/tenants/statistics')
        ->assertSuccessful()
        ->assertJsonPath('data.suspended', 1)
        ->assertJsonStructure([
            'data' => [
                'total',
                'active',
                'trial',
                'suspended',
                'pending',
                'archived',
                'trashed',
                'by_status',
            ],
        ]);
});

it('creates a tenant with an optional primary domain', function (): void {
    actingAsCentralUser(['tenants.create', 'tenants.view']);

    $response = $this->postJson('/api/v1/tenants', [
        'name' => 'Acme Commerce',
        'email' => 'ops@acme.test',
        'domain' => 'acme.test',
        'tags' => ['retail'],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Acme Commerce')
        ->assertJsonPath('data.slug', 'acme-commerce')
        ->assertJsonPath('data.status', TenantStatus::TRIAL->value);

    expect(Domain::query()->where('domain', 'acme.test')->where('is_primary', true)->exists())->toBeTrue();
    expect($response->json('data.id'))->not->toBeEmpty();
});

it('applies tenant onboarding and domain policy settings', function (): void {
    $this->seed(SettingSeeder::class);
    actingAsCentralUser(['tenants.create', 'tenants.view', 'settings.update']);

    app(SettingService::class)->bulkUpdate([
        'tenant.default_trial_days' => 21,
        'tenant.auto_generate_domain' => false,
        'tenant.allow_custom_domains' => false,
    ]);

    $response = $this->postJson('/api/v1/tenants', [
        'name' => 'Policy Tenant',
        'email' => 'owner@policy.test',
    ])->assertCreated();

    $tenant = Tenant::query()->findOrFail($response->json('data.id'));

    expect($tenant->domains()->count())->toBe(0)
        ->and($tenant->trial_ends_at?->toDateString())->toBe(now()->addDays(21)->toDateString());

    $this->postJson('/api/v1/tenants', [
        'name' => 'Blocked Custom Domain',
        'email' => 'owner@blocked.test',
        'domain' => 'customer.example.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['domain']);
});

it('suspends activates and archives a tenant', function (): void {
    actingAsCentralUser([
        'tenants.suspend',
        'tenants.activate',
        'tenants.archive',
        'tenants.view',
    ]);

    $tenant = Tenant::factory()->create();

    $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", [
        'reason' => 'Non-payment',
    ])->assertSuccessful()
        ->assertJsonPath('data.status', TenantStatus::SUSPENDED->value);

    $this->postJson("/api/v1/tenants/{$tenant->id}/activate")
        ->assertSuccessful()
        ->assertJsonPath('data.status', TenantStatus::ACTIVE->value);

    $this->postJson("/api/v1/tenants/{$tenant->id}/archive")
        ->assertSuccessful()
        ->assertJsonPath('data.status', TenantStatus::ARCHIVED->value);
});

it('soft deletes and restores a tenant', function (): void {
    actingAsCentralUser(['tenants.delete', 'tenants.restore', 'tenants.view']);
    $tenant = Tenant::factory()->create();

    $this->deleteJson("/api/v1/tenants/{$tenant->id}")
        ->assertSuccessful();

    expect($tenant->fresh()->trashed())->toBeTrue();

    $this->postJson("/api/v1/tenants/{$tenant->id}/restore")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $tenant->id);
});

it('bulk deletes suspends and activates tenants', function (): void {
    actingAsCentralUser([
        'tenants.delete',
        'tenants.suspend',
        'tenants.activate',
        'tenants.view',
    ]);

    $toDelete = Tenant::factory()->count(2)->create();
    $toSuspend = Tenant::factory()->count(2)->create(['status' => TenantStatus::ACTIVE]);
    $toActivate = Tenant::factory()->count(2)->create([
        'status' => TenantStatus::SUSPENDED,
        'suspended_at' => now(),
        'suspended_reason' => 'Pending review',
    ]);

    $this->deleteJson('/api/v1/tenants/bulk', [
        'ids' => $toDelete->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);

    expect(Tenant::onlyTrashed()->whereIn('id', $toDelete->pluck('id'))->count())->toBe(2);

    $this->postJson('/api/v1/tenants/bulk/suspend', [
        'ids' => $toSuspend->pluck('id')->all(),
        'reason' => 'Bulk review',
    ])->assertSuccessful()
        ->assertJsonPath('data.suspended', 2);

    expect(
        Tenant::query()
            ->whereIn('id', $toSuspend->pluck('id'))
            ->where('status', TenantStatus::SUSPENDED)
            ->where('suspended_reason', 'Bulk review')
            ->count()
    )->toBe(2);

    $this->postJson('/api/v1/tenants/bulk/activate', [
        'ids' => $toActivate->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.activated', 2);

    expect(
        Tenant::query()
            ->whereIn('id', $toActivate->pluck('id'))
            ->where('status', TenantStatus::ACTIVE)
            ->whereNull('suspended_at')
            ->whereNull('suspended_reason')
            ->count()
    )->toBe(2);
});

it('forbids bulk tenant actions without permission', function (): void {
    actingAsCentralUser(['tenants.view']);
    $tenants = Tenant::factory()->count(2)->create();

    $this->deleteJson('/api/v1/tenants/bulk', [
        'ids' => $tenants->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/tenants/bulk/suspend', [
        'ids' => $tenants->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/tenants/bulk/activate', [
        'ids' => $tenants->pluck('id')->all(),
    ])->assertForbidden();
});

it('manages notes tags and metadata', function (): void {
    actingAsCentralUser([
        'tenants.manage-notes',
        'tenants.manage-tags',
        'tenants.manage-metadata',
        'tenants.view',
    ]);

    $tenant = Tenant::factory()->create();

    $this->postJson("/api/v1/tenants/{$tenant->id}/notes", [
        'body' => 'Follow up on onboarding',
        'is_internal' => true,
    ])->assertCreated()
        ->assertJsonPath('data.body', 'Follow up on onboarding');

    $this->putJson("/api/v1/tenants/{$tenant->id}/tags", [
        'tags' => ['vip', 'retail'],
    ])->assertSuccessful()
        ->assertJsonPath('data.tags.0', 'vip');

    $this->putJson("/api/v1/tenants/{$tenant->id}/metadata", [
        'metadata' => ['tier' => 'gold'],
    ])->assertSuccessful()
        ->assertJsonPath('data.metadata.tier', 'gold');
});

it('returns statistics and health', function (): void {
    actingAsCentralUser(['tenants.view-stats', 'tenants.view-health']);
    $tenant = Tenant::factory()->create();
    Domain::factory()->primary()->create(['tenant_id' => $tenant->id, 'domain' => 'health.example.test']);

    $this->getJson("/api/v1/tenants/{$tenant->id}/statistics")
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['domains_count', 'can_access']]);

    $this->getJson("/api/v1/tenants/{$tenant->id}/health")
        ->assertSuccessful()
        ->assertJsonPath('data.healthy', true);
});

it('starts and revokes impersonation', function (): void {
    actingAsCentralUser(['tenants.impersonate']);
    $tenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);

    $started = $this->postJson("/api/v1/tenants/{$tenant->id}/impersonate", [
        'reason' => ImpersonationReason::SUPPORT->value,
        'reason_notes' => 'Ticket #123',
    ])->assertCreated();

    expect($started->json('data.token'))->not->toBeEmpty()
        ->and($started->json('data.url'))->toContain('impersonate');

    $impersonationId = $started->json('data.impersonation.id');

    $this->postJson("/api/v1/tenants/{$tenant->id}/impersonations/{$impersonationId}/revoke")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'revoked');

    expect(TenantImpersonation::query()->find($impersonationId)?->ended_at)->not->toBeNull();
});

it('forbids tenant listing without permission', function (): void {
    $user = \App\Models\User::factory()->create();
    test()->seed(\Database\Seeders\Central\RbacSeeder::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/tenants')
        ->assertForbidden();
});
