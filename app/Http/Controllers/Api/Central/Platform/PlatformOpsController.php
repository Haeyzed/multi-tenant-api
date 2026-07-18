<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Platform;

use App\Enums\Central\AIProvider;
use App\Enums\Central\BackupType;
use App\Enums\Central\ThemeStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\AiProviderSetting;
use App\Models\Central\Backup;
use App\Models\Central\BackupSchedule;
use App\Models\Central\InstalledIntegration;
use App\Models\Central\Integration;
use App\Models\Central\PlatformVersion;
use App\Models\Central\Theme;
use App\Models\Central\ThemeInstallation;
use App\Services\Central\Platform\PlatformOpsService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Central AI Providers', description: 'AI provider settings, limits, and credits.', weight: 240)]
final class PlatformOpsController extends Controller
{
    public function __construct(
        private readonly PlatformOpsService $platformOpsService,
    ) {}

    // AI
    #[Endpoint(operationId: 'platform.platformops.aiProviders', title: 'List AI providers', description: 'List configured AI provider settings.')]
    public function aiProviders(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ai.view'), 403);

        return $this->success($this->platformOpsService->listAiProviders(), 'AI providers retrieved successfully.');
    }

    #[Endpoint(operationId: 'platform.platformops.upsertAiProvider', title: 'Upsert AI provider', description: 'Create or update provider credentials, limits, and credits.')]
    public function upsertAiProvider(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ai.manage'), 403);

        $data = $request->validate([
            'provider' => ['required', Rule::enum(AIProvider::class)],
            'label' => ['sometimes', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'api_key' => ['sometimes', 'nullable', 'string'],
            'default_model' => ['sometimes', 'nullable', 'string'],
            'monthly_token_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'credits_remaining' => ['sometimes', 'numeric', 'min:0'],
            'config' => ['sometimes', 'array'],
        ]);

        $provider = $this->platformOpsService->upsertAiProvider($data);

        return $this->success($provider, 'AI provider saved successfully.');
    }

    #[Endpoint(operationId: 'platform.platformops.recordAiUsage', title: 'Record AI usage', description: 'Increment token usage and optionally deduct credits.')]
    public function recordAiUsage(Request $request, AiProviderSetting $aiProvider): JsonResponse
    {
        abort_unless($request->user()?->can('ai.manage'), 403);

        $data = $request->validate([
            'tokens' => ['required', 'integer', 'min:1'],
            'credit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $provider = $this->platformOpsService->recordAiUsage(
            $aiProvider,
            $data['tokens'],
            (float) ($data['credit_cost'] ?? 0),
        );

        return $this->success($provider, 'AI usage recorded successfully.');
    }

    // Integrations
    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.integrations', title: 'List integrations', description: 'Paginate marketplace/integration catalog entries.')]
    public function integrations(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('integrations.view'), 403);

        return $this->paginated(
            $this->platformOpsService->paginateIntegrations($request->only(['search', 'status', 'marketplace', 'per_page'])),
            'Integrations retrieved successfully.',
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.storeIntegration', title: 'Create integration', description: 'Add an integration to the marketplace catalog.')]
    public function storeIntegration(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('integrations.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:integrations,slug'],
            'vendor' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'version' => ['sometimes', 'string'],
            'is_marketplace' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'permissions' => ['sometimes', 'array'],
            'config_schema' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->createIntegration($data),
            'Integration created successfully.',
            201,
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.installIntegration', title: 'Install integration', description: 'Install an integration for a tenant or centrally.')]
    public function installIntegration(Request $request, Integration $integration): JsonResponse
    {
        abort_unless($request->user()?->can('integrations.manage'), 403);

        $data = $request->validate([
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
            'configuration' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->installIntegration($integration, $data, $request->user()),
            'Integration installed successfully.',
            201,
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.activateInstallation', title: 'Activate installation', description: 'Activate an installed integration.')]
    public function activateInstallation(Request $request, InstalledIntegration $installation): JsonResponse
    {
        abort_unless($request->user()?->can('integrations.manage'), 403);

        return $this->success(
            $this->platformOpsService->activateInstallation($installation),
            'Integration activated successfully.',
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.configureInstallation', title: 'Configure installation', description: 'Update configuration for an installed integration.')]
    public function configureInstallation(Request $request, InstalledIntegration $installation): JsonResponse
    {
        abort_unless($request->user()?->can('integrations.manage'), 403);

        $data = $request->validate([
            'configuration' => ['required', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->configureInstallation($installation, $data['configuration']),
            'Integration configured successfully.',
        );
    }

    // Themes
    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.themes', title: 'List themes', description: 'Paginate theme marketplace entries.')]
    public function themes(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('themes.view'), 403);

        return $this->paginated(
            $this->platformOpsService->paginateThemes($request->only(['search', 'status', 'per_page'])),
            'Themes retrieved successfully.',
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.storeTheme', title: 'Create theme', description: 'Create a theme entry.')]
    public function storeTheme(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('themes.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:themes,slug'],
            'description' => ['sometimes', 'nullable', 'string'],
            'version' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(ThemeStatus::class)],
            'preview_url' => ['sometimes', 'nullable', 'url'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'author' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->createTheme($data),
            'Theme created successfully.',
            201,
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.publishTheme', title: 'Publish theme', description: 'Publish a theme so it can be installed.')]
    public function publishTheme(Request $request, Theme $theme): JsonResponse
    {
        abort_unless($request->user()?->can('themes.manage'), 403);

        return $this->success(
            $this->platformOpsService->publishTheme($theme),
            'Theme published successfully.',
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.installTheme', title: 'Install theme', description: 'Install a published theme for a tenant.')]
    public function installTheme(Request $request, Theme $theme): JsonResponse
    {
        abort_unless($request->user()?->can('themes.manage'), 403);

        $data = $request->validate([
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
        ]);

        return $this->success(
            $this->platformOpsService->installTheme($theme, $data, $request->user()),
            'Theme installed successfully.',
            201,
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.activateTheme', title: 'Activate theme', description: 'Activate an installed theme (deactivates siblings).')]
    public function activateTheme(Request $request, ThemeInstallation $installation): JsonResponse
    {
        abort_unless($request->user()?->can('themes.manage'), 403);

        return $this->success(
            $this->platformOpsService->activateTheme($installation),
            'Theme activated successfully.',
        );
    }

    // Backups
    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.backups', title: 'List backups', description: 'Paginate backup records.')]
    public function backups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.view'), 403);

        return $this->paginated(
            $this->platformOpsService->paginateBackups($request->only(['status', 'type', 'per_page'])),
            'Backups retrieved successfully.',
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.storeBackup', title: 'Create backup', description: 'Run a manual backup snapshot and mark it completed.')]
    public function storeBackup(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(BackupType::class)],
            'disk' => ['sometimes', 'string'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->createBackup($data, $request->user()),
            'Backup created successfully.',
            201,
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.restoreBackup', title: 'Restore backup', description: 'Mark a completed backup as restored.')]
    public function restoreBackup(Request $request, Backup $backup): JsonResponse
    {
        abort_unless($request->user()?->can('backups.manage'), 403);

        return $this->success(
            $this->platformOpsService->restoreBackup($backup, $request->user()),
            'Backup restored successfully.',
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.backupSchedules', title: 'List backup schedules', description: 'List automatic backup schedules.')]
    public function backupSchedules(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.view'), 403);

        return $this->success($this->platformOpsService->schedules(), 'Backup schedules retrieved successfully.');
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.storeBackupSchedule', title: 'Create backup schedule', description: 'Create a cron-based backup schedule with retention.')]
    public function storeBackupSchedule(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(BackupType::class)],
            'cron_expression' => ['sometimes', 'string'],
            'retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->createSchedule($data),
            'Backup schedule created successfully.',
            201,
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.applyRetention', title: 'Apply retention', description: 'Delete automatic backups older than retention days.')]
    public function applyRetention(Request $request, BackupSchedule $schedule): JsonResponse
    {
        abort_unless($request->user()?->can('backups.manage'), 403);

        $deleted = $this->platformOpsService->applyRetention($schedule);

        return $this->success(['deleted' => $deleted], 'Backup retention applied successfully.');
    }

    // Versions
    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.versions', title: 'List platform versions', description: 'Paginate platform version records.')]
    public function versions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('versions.view'), 403);

        return $this->paginated(
            $this->platformOpsService->paginateVersions((int) $request->integer('per_page', 15)),
            'Platform versions retrieved successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.currentVersion', title: 'Current platform version', description: 'Return the currently released platform version.')]
    public function currentVersion(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('versions.view'), 403);

        return $this->success(
            $this->platformOpsService->currentVersion(),
            'Current platform version retrieved successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.storeVersion', title: 'Create platform version', description: 'Create a draft platform version with release notes.')]
    public function storeVersion(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('versions.manage'), 403);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:50', 'unique:platform_versions,version'],
            'release_notes' => ['sometimes', 'nullable', 'string'],
            'migration_status' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return $this->success(
            $this->platformOpsService->createVersion($data),
            'Platform version created successfully.',
            201,
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.releaseVersion', title: 'Release version', description: 'Release a version and mark it current.')]
    public function releaseVersion(Request $request, PlatformVersion $version): JsonResponse
    {
        abort_unless($request->user()?->can('versions.manage'), 403);

        return $this->success(
            $this->platformOpsService->releaseVersion($version),
            'Platform version released successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.rollbackVersion', title: 'Rollback version', description: 'Roll back the current release to a previous version.')]
    public function rollbackVersion(Request $request, PlatformVersion $version): JsonResponse
    {
        abort_unless($request->user()?->can('versions.manage'), 403);

        $data = $request->validate([
            'target_version_id' => ['required', 'integer', 'exists:platform_versions,id'],
        ]);

        $target = PlatformVersion::query()->findOrFail($data['target_version_id']);

        return $this->success(
            $this->platformOpsService->rollbackVersion($version, $target),
            'Platform version rolled back successfully.',
        );
    }
}

