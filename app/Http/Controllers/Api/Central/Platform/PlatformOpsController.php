<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Platform\ConfigureInstallationRequest;
use App\Http\Requests\Central\Platform\InstallIntegrationRequest;
use App\Http\Requests\Central\Platform\InstallThemeRequest;
use App\Http\Requests\Central\Platform\RecordAiUsageRequest;
use App\Http\Requests\Central\Platform\RollbackVersionRequest;
use App\Http\Requests\Central\Platform\StoreBackupRequest;
use App\Http\Requests\Central\Platform\StoreBackupScheduleRequest;
use App\Http\Requests\Central\Platform\StoreIntegrationRequest;
use App\Http\Requests\Central\Platform\StoreThemeRequest;
use App\Http\Requests\Central\Platform\StoreVersionRequest;
use App\Http\Requests\Central\Platform\UpsertAiProviderRequest;
use App\Models\Central\AiProviderSetting;
use App\Models\Central\Backup;
use App\Models\Central\BackupSchedule;
use App\Models\Central\InstalledIntegration;
use App\Models\Central\Integration;
use App\Models\Central\PlatformVersion;
use App\Models\Central\Theme;
use App\Models\Central\ThemeInstallation;
use App\Services\Central\Platform\PlatformOpsService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $this->authorize('viewAi');

        return $this->success($this->platformOpsService->listAiProviders(), 'AI providers retrieved successfully.');
    }

    #[Endpoint(operationId: 'platform.platformops.upsertAiProvider', title: 'Upsert AI provider', description: 'Create or update provider credentials, limits, and credits.')]
    public function upsertAiProvider(UpsertAiProviderRequest $request): JsonResponse
    {
        $this->authorize('manageAi');

        $provider = $this->platformOpsService->upsertAiProvider($request->validated());

        return $this->success($provider, 'AI provider saved successfully.');
    }

    #[Endpoint(operationId: 'platform.platformops.recordAiUsage', title: 'Record AI usage', description: 'Increment token usage and optionally deduct credits.')]
    public function recordAiUsage(RecordAiUsageRequest $request, AiProviderSetting $aiProvider): JsonResponse
    {
        $this->authorize('manageAi');

        $data = $request->validated();

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
        $this->authorize('viewIntegrations');

        return $this->paginated(
            $this->platformOpsService->paginateIntegrations($request->only(['search', 'status', 'marketplace', 'per_page'])),
            'Integrations retrieved successfully.',
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.storeIntegration', title: 'Create integration', description: 'Add an integration to the marketplace catalog.')]
    public function storeIntegration(StoreIntegrationRequest $request): JsonResponse
    {
        $this->authorize('manageIntegrations');

        return $this->success(
            $this->platformOpsService->createIntegration($request->validated()),
            'Integration created successfully.',
            201,
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.installIntegration', title: 'Install integration', description: 'Install an integration for a tenant or centrally.')]
    public function installIntegration(InstallIntegrationRequest $request, Integration $integration): JsonResponse
    {
        $this->authorize('manageIntegrations');

        return $this->success(
            $this->platformOpsService->installIntegration($integration, $request->validated(), $request->user()),
            'Integration installed successfully.',
            201,
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.activateInstallation', title: 'Activate installation', description: 'Activate an installed integration.')]
    public function activateInstallation(Request $request, InstalledIntegration $installation): JsonResponse
    {
        $this->authorize('manageIntegrations');

        return $this->success(
            $this->platformOpsService->activateInstallation($installation),
            'Integration activated successfully.',
        );
    }

    #[Group('Central Integrations', description: 'Marketplace integrations and installations.', weight: 250)]
    #[Endpoint(operationId: 'platform.platformops.configureInstallation', title: 'Configure installation', description: 'Update configuration for an installed integration.')]
    public function configureInstallation(ConfigureInstallationRequest $request, InstalledIntegration $installation): JsonResponse
    {
        $this->authorize('manageIntegrations');

        $data = $request->validated();

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
        $this->authorize('viewThemes');

        return $this->paginated(
            $this->platformOpsService->paginateThemes($request->only(['search', 'status', 'per_page'])),
            'Themes retrieved successfully.',
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.storeTheme', title: 'Create theme', description: 'Create a theme entry.')]
    public function storeTheme(StoreThemeRequest $request): JsonResponse
    {
        $this->authorize('manageThemes');

        return $this->success(
            $this->platformOpsService->createTheme($request->validated()),
            'Theme created successfully.',
            201,
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.publishTheme', title: 'Publish theme', description: 'Publish a theme so it can be installed.')]
    public function publishTheme(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('manageThemes');

        return $this->success(
            $this->platformOpsService->publishTheme($theme),
            'Theme published successfully.',
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.installTheme', title: 'Install theme', description: 'Install a published theme for a tenant.')]
    public function installTheme(InstallThemeRequest $request, Theme $theme): JsonResponse
    {
        $this->authorize('manageThemes');

        return $this->success(
            $this->platformOpsService->installTheme($theme, $request->validated(), $request->user()),
            'Theme installed successfully.',
            201,
        );
    }

    #[Group('Central Themes', description: 'Theme marketplace, install, and activate.', weight: 260)]
    #[Endpoint(operationId: 'platform.platformops.activateTheme', title: 'Activate theme', description: 'Activate an installed theme (deactivates siblings).')]
    public function activateTheme(Request $request, ThemeInstallation $installation): JsonResponse
    {
        $this->authorize('manageThemes');

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
        $this->authorize('viewBackups');

        return $this->paginated(
            $this->platformOpsService->paginateBackups($request->only(['status', 'type', 'per_page'])),
            'Backups retrieved successfully.',
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.storeBackup', title: 'Create backup', description: 'Run a manual backup snapshot and mark it completed.')]
    public function storeBackup(StoreBackupRequest $request): JsonResponse
    {
        $this->authorize('manageBackups');

        return $this->success(
            $this->platformOpsService->createBackup($request->validated(), $request->user()),
            'Backup created successfully.',
            201,
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.restoreBackup', title: 'Restore backup', description: 'Mark a completed backup as restored.')]
    public function restoreBackup(Request $request, Backup $backup): JsonResponse
    {
        $this->authorize('manageBackups');

        return $this->success(
            $this->platformOpsService->restoreBackup($backup, $request->user()),
            'Backup restored successfully.',
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.backupSchedules', title: 'List backup schedules', description: 'List automatic backup schedules.')]
    public function backupSchedules(Request $request): JsonResponse
    {
        $this->authorize('viewBackups');

        return $this->success($this->platformOpsService->schedules(), 'Backup schedules retrieved successfully.');
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.storeBackupSchedule', title: 'Create backup schedule', description: 'Create a cron-based backup schedule with retention.')]
    public function storeBackupSchedule(StoreBackupScheduleRequest $request): JsonResponse
    {
        $this->authorize('manageBackups');

        return $this->success(
            $this->platformOpsService->createSchedule($request->validated()),
            'Backup schedule created successfully.',
            201,
        );
    }

    #[Group('Central Backups', description: 'Manual/automatic backups, restore, schedules.', weight: 270)]
    #[Endpoint(operationId: 'platform.platformops.applyRetention', title: 'Apply retention', description: 'Delete automatic backups older than retention days.')]
    public function applyRetention(Request $request, BackupSchedule $schedule): JsonResponse
    {
        $this->authorize('manageBackups');

        $deleted = $this->platformOpsService->applyRetention($schedule);

        return $this->success(['deleted' => $deleted], 'Backup retention applied successfully.');
    }

    // Versions
    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.versions', title: 'List platform versions', description: 'Paginate platform version records.')]
    public function versions(Request $request): JsonResponse
    {
        $this->authorize('viewVersions');

        return $this->paginated(
            $this->platformOpsService->paginateVersions((int) $request->integer('per_page', 15)),
            'Platform versions retrieved successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.currentVersion', title: 'Current platform version', description: 'Return the currently released platform version.')]
    public function currentVersion(Request $request): JsonResponse
    {
        $this->authorize('viewVersions');

        return $this->success(
            $this->platformOpsService->currentVersion(),
            'Current platform version retrieved successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.storeVersion', title: 'Create platform version', description: 'Create a draft platform version with release notes.')]
    public function storeVersion(StoreVersionRequest $request): JsonResponse
    {
        $this->authorize('manageVersions');

        return $this->success(
            $this->platformOpsService->createVersion($request->validated()),
            'Platform version created successfully.',
            201,
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.releaseVersion', title: 'Release version', description: 'Release a version and mark it current.')]
    public function releaseVersion(Request $request, PlatformVersion $version): JsonResponse
    {
        $this->authorize('manageVersions');

        return $this->success(
            $this->platformOpsService->releaseVersion($version),
            'Platform version released successfully.',
        );
    }

    #[Group('Central Versions', description: 'Platform versioning, release notes, rollback.', weight: 280)]
    #[Endpoint(operationId: 'platform.platformops.rollbackVersion', title: 'Rollback version', description: 'Roll back the current release to a previous version.')]
    public function rollbackVersion(RollbackVersionRequest $request, PlatformVersion $version): JsonResponse
    {
        $this->authorize('manageVersions');

        $target = PlatformVersion::query()->findOrFail($request->validated('target_version_id'));

        return $this->success(
            $this->platformOpsService->rollbackVersion($version, $target),
            'Platform version rolled back successfully.',
        );
    }
}
