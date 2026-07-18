<?php

declare(strict_types=1);

use App\Enums\Central\DomainStatus;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;

it('creates verifies and manages domains for a tenant', function (): void {
    actingAsCentralUser([
        'domains.view',
        'domains.create',
        'domains.update',
        'domains.delete',
        'domains.verify',
        'domains.manage-ssl',
        'domains.manage-primary',
    ]);

    $tenant = Tenant::factory()->create();

    $created = $this->postJson("/api/v1/tenants/{$tenant->id}/domains", [
        'domain' => 'shop.example.test',
        'is_primary' => true,
    ])->assertCreated()
        ->assertJsonPath('data.domain', 'shop.example.test')
        ->assertJsonPath('data.is_primary', true);

    $domainId = $created->json('data.id');

    $this->postJson("/api/v1/tenants/{$tenant->id}/domains/{$domainId}/verify-dns")
        ->assertSuccessful()
        ->assertJsonPath('data.status', DomainStatus::ACTIVE->value);

    $this->postJson("/api/v1/tenants/{$tenant->id}/domains/{$domainId}/ssl/enable")
        ->assertSuccessful()
        ->assertJsonPath('data.ssl_enabled', true);

    $secondary = $this->postJson("/api/v1/tenants/{$tenant->id}/domains", [
        'domain' => 'alias.example.test',
    ])->assertCreated();

    $secondaryId = $secondary->json('data.id');

    $this->postJson("/api/v1/tenants/{$tenant->id}/domains/{$secondaryId}/primary")
        ->assertSuccessful()
        ->assertJsonPath('data.is_primary', true);

    expect(Domain::query()->find($domainId)?->is_primary)->toBeFalse();

    $this->putJson("/api/v1/tenants/{$tenant->id}/domains/{$domainId}/redirect", [
        'redirect_to' => 'https://alias.example.test',
    ])->assertSuccessful()
        ->assertJsonPath('data.is_redirect', true);

    $this->deleteJson("/api/v1/tenants/{$tenant->id}/domains/{$domainId}")
        ->assertSuccessful();
});

it('lists domains for a tenant', function (): void {
    actingAsCentralUser(['domains.view']);
    $tenant = Tenant::factory()->create();
    Domain::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $this->getJson("/api/v1/tenants/{$tenant->id}/domains")
        ->assertSuccessful()
        ->assertJsonPath('status', true);
});
