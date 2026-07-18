<?php

declare(strict_types=1);

use App\Enums\Central\AIProvider;
use App\Enums\Central\BackupStatus;
use App\Enums\Central\ThemeStatus;
use App\Enums\Central\WebhookEvent;
use App\Enums\Central\WebhookStatus;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('returns monitoring overview and subsystem health', function (): void {
    actingAsCentralUser(['monitoring.view', 'monitoring.manage']);

    $this->getJson('/api/v1/monitoring')
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'healthy');

    $this->getJson('/api/v1/monitoring/queue')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->getJson('/api/v1/monitoring/server')
        ->assertSuccessful()
        ->assertJsonPath('data.php_version', PHP_VERSION);

    $this->getJson('/api/v1/monitoring/failed-jobs')
        ->assertSuccessful();
});

it('manages api clients webhooks and delivery retries', function (): void {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    actingAsCentralUser([
        'api.clients.view',
        'api.clients.manage',
        'api.webhooks.view',
        'api.webhooks.manage',
    ]);

    $client = $this->postJson('/api/v1/api-clients', [
        'name' => 'Billing Sync',
        'scopes' => ['read', 'write'],
        'rate_limit_per_minute' => 120,
    ])->assertCreated()
        ->assertJsonPath('data.client.name', 'Billing Sync');

    expect($client->json('data.client_secret'))->not->toBeEmpty();

    $clientId = $client->json('data.client.id');

    $webhook = $this->postJson('/api/v1/webhooks', [
        'name' => 'Tenant hooks',
        'url' => 'https://example.test/hooks',
        'events' => [WebhookEvent::TENANT_CREATED->value],
        'api_client_id' => $clientId,
    ])->assertCreated();

    $webhookId = $webhook->json('data.webhook.id');

    $delivery = $this->postJson("/api/v1/webhooks/{$webhookId}/dispatch", [
        'event' => WebhookEvent::TENANT_CREATED->value,
        'payload' => ['tenant_id' => 'abc'],
    ])->assertCreated()
        ->assertJsonPath('data.status', WebhookStatus::DELIVERED->value);

    $this->getJson("/api/v1/webhooks/{$webhookId}/deliveries")
        ->assertSuccessful();

    $this->getJson('/api/v1/webhook-events')
        ->assertSuccessful();

    expect($delivery->json('data.id'))->toBeInt();
});

it('manages ai integrations themes backups and versions', function (): void {
    Storage::fake('local');

    actingAsCentralUser([
        'ai.view',
        'ai.manage',
        'integrations.view',
        'integrations.manage',
        'themes.view',
        'themes.manage',
        'backups.view',
        'backups.manage',
        'versions.view',
        'versions.manage',
    ]);

    $tenant = Tenant::factory()->create();

    $this->putJson('/api/v1/ai-providers', [
        'provider' => AIProvider::OPENAI->value,
        'is_enabled' => true,
        'api_key' => 'sk-test',
        'monthly_token_limit' => 10000,
        'credits_remaining' => 50,
    ])->assertSuccessful()
        ->assertJsonPath('data.provider', AIProvider::OPENAI->value);

    $aiId = $this->getJson('/api/v1/ai-providers')->json('data.0.id');

    $this->postJson("/api/v1/ai-providers/{$aiId}/usage", [
        'tokens' => 100,
        'credit_cost' => 0.5,
    ])->assertSuccessful()
        ->assertJsonPath('data.monthly_token_usage', 100);

    $integration = $this->postJson('/api/v1/integrations', [
        'name' => 'Slack Connect',
        'vendor' => 'Slack',
        'version' => '1.2.0',
    ])->assertCreated();

    $installation = $this->postJson('/api/v1/integrations/'.$integration->json('data.id').'/install', [
        'tenant_id' => $tenant->id,
        'configuration' => ['channel' => '#ops'],
    ])->assertCreated();

    $this->postJson('/api/v1/installed-integrations/'.$installation->json('data.id').'/activate')
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active');

    $theme = $this->postJson('/api/v1/themes', [
        'name' => 'Aurora',
        'status' => ThemeStatus::DRAFT->value,
    ])->assertCreated();

    $themeId = $theme->json('data.id');

    $this->postJson("/api/v1/themes/{$themeId}/publish")
        ->assertSuccessful()
        ->assertJsonPath('data.status', ThemeStatus::PUBLISHED->value);

    $themeInstall = $this->postJson("/api/v1/themes/{$themeId}/install", [
        'tenant_id' => $tenant->id,
    ])->assertCreated();

    $this->postJson('/api/v1/theme-installations/'.$themeInstall->json('data.id').'/activate')
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', true);

    $backup = $this->postJson('/api/v1/backups', [
        'type' => 'full',
        'name' => 'nightly-test',
    ])->assertCreated()
        ->assertJsonPath('data.status', BackupStatus::COMPLETED->value);

    $this->postJson('/api/v1/backups/'.$backup->json('data.id').'/restore')
        ->assertSuccessful()
        ->assertJsonPath('data.status', BackupStatus::RESTORED->value);

    $this->postJson('/api/v1/backup-schedules', [
        'name' => 'Nightly',
        'retention_days' => 14,
    ])->assertCreated();

    $v1 = $this->postJson('/api/v1/platform-versions', [
        'version' => '1.0.0',
        'release_notes' => 'Initial release',
    ])->assertCreated();

    $v2 = $this->postJson('/api/v1/platform-versions', [
        'version' => '1.1.0',
        'release_notes' => 'Phase 7',
    ])->assertCreated();

    $this->postJson('/api/v1/platform-versions/'.$v1->json('data.id').'/release')
        ->assertSuccessful()
        ->assertJsonPath('data.is_current', true);

    $this->postJson('/api/v1/platform-versions/'.$v2->json('data.id').'/release')
        ->assertSuccessful()
        ->assertJsonPath('data.is_current', true);

    $this->postJson('/api/v1/platform-versions/'.$v2->json('data.id').'/rollback', [
        'target_version_id' => $v1->json('data.id'),
    ])->assertSuccessful()
        ->assertJsonPath('data.version', '1.0.0')
        ->assertJsonPath('data.is_current', true);

    $this->getJson('/api/v1/platform-versions/current')
        ->assertSuccessful()
        ->assertJsonPath('data.version', '1.0.0');
});
