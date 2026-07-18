<?php

declare(strict_types=1);

namespace App\Services\Central\Platform;

use App\Enums\Central\AIProvider;
use App\Enums\Central\BackupStatus;
use App\Enums\Central\BackupType;
use App\Enums\Central\IntegrationStatus;
use App\Enums\Central\PlatformVersionStatus;
use App\Enums\Central\ThemeStatus;
use App\Models\Central\AiProviderSetting;
use App\Models\Central\Backup;
use App\Models\Central\BackupSchedule;
use App\Models\Central\InstalledIntegration;
use App\Models\Central\Integration;
use App\Models\Central\PlatformVersion;
use App\Models\Central\Theme;
use App\Models\Central\ThemeInstallation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for central platform operations.
 *
 * Encapsulates AI provider settings, integrations, themes, backups,
 * backup schedules, and platform version management so controllers remain thin.
 */
final class PlatformOpsService
{
    // ── AI Providers ──────────────────────────────────────────────

    /**
     * List all configured AI provider settings.
     *
     * @return Collection<int, AiProviderSetting>
     */
    public function listAiProviders(): Collection
    {
        return AiProviderSetting::query()->orderBy('provider')->get();
    }

    /**
     * Create or update an AI provider configuration.
     *
     * @param array<string, mixed> $data
     * @return AiProviderSetting
     */
    public function upsertAiProvider(array $data): AiProviderSetting
    {
        $provider = $data['provider'] instanceof AIProvider
            ? $data['provider']
            : AIProvider::from($data['provider']);

        return AiProviderSetting::query()->updateOrCreate(
            ['provider' => $provider],
            [
                'label' => $data['label'] ?? $provider->label(),
                'is_enabled' => $data['is_enabled'] ?? false,
                'api_key' => $data['api_key'] ?? null,
                'default_model' => $data['default_model'] ?? $provider->defaultModel(),
                'monthly_token_limit' => $data['monthly_token_limit'] ?? null,
                'credits_remaining' => $data['credits_remaining'] ?? 0,
                'config' => $data['config'] ?? [],
            ],
        );
    }

    /**
     * Record AI token usage and deduct credits from a provider setting.
     *
     * @param AiProviderSetting $setting
     * @param int $tokens
     * @param float $creditCost
     * @return AiProviderSetting
     *
     * @throws ValidationException
     */
    public function recordAiUsage(AiProviderSetting $setting, int $tokens, float $creditCost = 0): AiProviderSetting
    {
        if ($setting->monthly_token_limit !== null
            && ($setting->monthly_token_usage + $tokens) > $setting->monthly_token_limit) {
            throw ValidationException::withMessages([
                'usage' => ['Monthly token limit exceeded.'],
            ]);
        }

        $setting->update([
            'monthly_token_usage' => $setting->monthly_token_usage + $tokens,
            'credits_remaining' => max(0, (float)$setting->credits_remaining - $creditCost),
        ]);

        return $setting->refresh();
    }

    // ── Integrations ──────────────────────────────────────────────

    /**
     * Paginate marketplace integrations with optional filters.
     *
     * @param array{search?: string, status?: string, marketplace?: bool, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Integration>
     */
    public function paginateIntegrations(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Integration::query()
            ->withCount('installations')
            ->when($filters['search'] ?? null, fn($q, string $s) => $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"))
            ->when($filters['status'] ?? null, fn($q, string $status) => $q->where('status', $status))
            ->when(
                array_key_exists('marketplace', $filters) && $filters['marketplace'] !== null,
                fn($q) => $q->where('is_marketplace', (bool)$filters['marketplace'])
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create a new integration catalog entry.
     *
     * @param array<string, mixed> $data
     * @return Integration
     */
    public function createIntegration(array $data): Integration
    {
        return Integration::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'vendor' => $data['vendor'] ?? null,
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? '1.0.0',
            'status' => $data['status'] ?? IntegrationStatus::ACTIVE->value,
            'is_marketplace' => $data['is_marketplace'] ?? true,
            'price' => $data['price'] ?? 0,
            'permissions' => $data['permissions'] ?? [],
            'config_schema' => $data['config_schema'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Install or update an integration for a tenant.
     *
     * @param Integration $integration
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return InstalledIntegration
     */
    public function installIntegration(Integration $integration, array $data, ?User $actor = null): InstalledIntegration
    {
        return InstalledIntegration::query()->updateOrCreate(
            [
                'integration_id' => $integration->id,
                'tenant_id' => $data['tenant_id'] ?? null,
            ],
            [
                'status' => IntegrationStatus::PENDING,
                'installed_version' => $integration->version,
                'configuration' => $data['configuration'] ?? [],
                'installed_by' => $actor?->id,
            ],
        );
    }

    /**
     * Activate a pending integration installation.
     *
     * @param InstalledIntegration $installation
     * @return InstalledIntegration
     */
    public function activateInstallation(InstalledIntegration $installation): InstalledIntegration
    {
        $installation->update([
            'status' => IntegrationStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        return $installation->refresh()->load('integration');
    }

    /**
     * Update configuration for an installed integration.
     *
     * @param InstalledIntegration $installation
     * @param array<string, mixed> $configuration
     * @return InstalledIntegration
     */
    public function configureInstallation(InstalledIntegration $installation, array $configuration): InstalledIntegration
    {
        $installation->update(['configuration' => $configuration]);

        return $installation->refresh();
    }

    // ── Themes ────────────────────────────────────────────────────

    /**
     * Paginate platform themes with optional filters.
     *
     * @param array{search?: string, status?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Theme>
     */
    public function paginateThemes(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Theme::query()
            ->withCount('installations')
            ->when($filters['search'] ?? null, fn($q, string $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($filters['status'] ?? null, fn($q, string $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create a new platform theme.
     *
     * @param array<string, mixed> $data
     * @return Theme
     */
    public function createTheme(array $data): Theme
    {
        return Theme::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? '1.0.0',
            'status' => $data['status'] ?? ThemeStatus::DRAFT->value,
            'preview_url' => $data['preview_url'] ?? null,
            'price' => $data['price'] ?? 0,
            'author' => $data['author'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Publish a draft theme for marketplace installation.
     *
     * @param Theme $theme
     * @return Theme
     */
    public function publishTheme(Theme $theme): Theme
    {
        $theme->update(['status' => ThemeStatus::PUBLISHED]);

        return $theme->refresh();
    }

    /**
     * Install a published theme for a tenant.
     *
     * @param Theme $theme
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return ThemeInstallation
     *
     * @throws ValidationException
     */
    public function installTheme(Theme $theme, array $data, ?User $actor = null): ThemeInstallation
    {
        if ($theme->status !== ThemeStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'theme' => ['Only published themes can be installed.'],
            ]);
        }

        return ThemeInstallation::query()->updateOrCreate(
            [
                'theme_id' => $theme->id,
                'tenant_id' => $data['tenant_id'] ?? null,
            ],
            [
                'is_active' => false,
                'installed_version' => $theme->version,
                'installed_by' => $actor?->id,
            ],
        );
    }

    /**
     * Activate a theme installation and deactivate siblings for the tenant.
     *
     * @param ThemeInstallation $installation
     * @return ThemeInstallation
     */
    public function activateTheme(ThemeInstallation $installation): ThemeInstallation
    {
        return DB::transaction(function () use ($installation): ThemeInstallation {
            ThemeInstallation::query()
                ->where('tenant_id', $installation->tenant_id)
                ->where('id', '!=', $installation->id)
                ->update(['is_active' => false]);

            $installation->update([
                'is_active' => true,
                'activated_at' => now(),
            ]);

            return $installation->refresh()->load('theme');
        });
    }

    // ── Backups ───────────────────────────────────────────────────

    /**
     * Paginate platform backups with optional filters.
     *
     * @param array{status?: string, type?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Backup>
     */
    public function paginateBackups(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Backup::query()
            ->when($filters['status'] ?? null, fn($q, string $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn($q, string $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Create and simulate completion of a platform backup.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @param bool $automatic
     * @return Backup
     */
    public function createBackup(array $data, ?User $actor = null, bool $automatic = false): Backup
    {
        $type = $data['type'] ?? BackupType::FULL->value;
        $name = $data['name'] ?? 'backup-' . now()->format('Ymd-His');

        $backup = Backup::query()->create([
            'name' => $name,
            'type' => $type,
            'status' => BackupStatus::RUNNING,
            'disk' => $data['disk'] ?? 'local',
            'is_automatic' => $automatic,
            'started_at' => now(),
            'created_by' => $actor?->id,
            'metadata' => $data['metadata'] ?? [],
        ]);

        $path = 'backups/' . $backup->id . '/' . $name . '.json';
        $payload = json_encode([
            'backup_id' => $backup->id,
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'snapshot' => ['simulated' => true],
        ], JSON_PRETTY_PRINT);

        Storage::disk($backup->disk)->put($path, $payload ?: '{}');

        $backup->update([
            'status' => BackupStatus::COMPLETED,
            'path' => $path,
            'size_bytes' => Storage::disk($backup->disk)->size($path),
            'completed_at' => now(),
        ]);

        return $backup->refresh();
    }

    /**
     * Restore a completed backup (simulated).
     *
     * @param Backup $backup
     * @param User|null $actor
     * @return Backup
     *
     * @throws ValidationException
     */
    public function restoreBackup(Backup $backup, ?User $actor = null): Backup
    {
        if (!in_array($backup->status, [BackupStatus::COMPLETED, BackupStatus::VERIFIED, BackupStatus::RESTORED], true)) {
            throw ValidationException::withMessages([
                'backup' => ['Only completed backups can be restored.'],
            ]);
        }

        $backup->update([
            'status' => BackupStatus::RESTORING,
        ]);

        $backup->update([
            'status' => BackupStatus::RESTORED,
            'restored_at' => now(),
            'metadata' => array_merge($backup->metadata ?? [], [
                'restored_by' => $actor?->id,
            ]),
        ]);

        return $backup->refresh();
    }

    /**
     * Create a recurring backup schedule.
     *
     * @param array<string, mixed> $data
     * @return BackupSchedule
     */
    public function createSchedule(array $data): BackupSchedule
    {
        return BackupSchedule::query()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? BackupType::FULL->value,
            'cron_expression' => $data['cron_expression'] ?? '0 2 * * *',
            'retention_days' => $data['retention_days'] ?? 30,
            'is_active' => $data['is_active'] ?? true,
            'next_run_at' => now()->addDay()->startOfDay()->setTime(2, 0),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * List all backup schedules ordered by name.
     *
     * @return Collection<int, BackupSchedule>
     */
    public function schedules(): Collection
    {
        return BackupSchedule::query()->orderBy('name')->get();
    }

    /**
     * Delete automatic backups older than the schedule retention period.
     *
     * @param BackupSchedule $schedule
     * @return int  Number of deleted backup records
     */
    public function applyRetention(BackupSchedule $schedule): int
    {
        $cutoff = now()->subDays($schedule->retention_days);

        return Backup::query()
            ->where('is_automatic', true)
            ->where('type', $schedule->type)
            ->where('completed_at', '<', $cutoff)
            ->delete();
    }

    // ── Versions ──────────────────────────────────────────────────

    /**
     * Paginate platform version records.
     *
     * @param int $perPage
     * @return LengthAwarePaginator<int, PlatformVersion>
     */
    public function paginateVersions(int $perPage = 15): LengthAwarePaginator
    {
        return PlatformVersion::query()->latest('id')->paginate(min($perPage, 100));
    }

    /**
     * Create a new draft platform version.
     *
     * @param array<string, mixed> $data
     * @return PlatformVersion
     */
    public function createVersion(array $data): PlatformVersion
    {
        return PlatformVersion::query()->create([
            'version' => $data['version'],
            'status' => PlatformVersionStatus::Draft,
            'release_notes' => $data['release_notes'] ?? null,
            'migration_status' => $data['migration_status'] ?? [
                    'pending' => 0,
                    'ran' => 0,
                ],
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Release a platform version and mark it as current.
     *
     * Deprecates the previously current version within a transaction.
     *
     * @param PlatformVersion $version
     * @return PlatformVersion
     */
    public function releaseVersion(PlatformVersion $version): PlatformVersion
    {
        return DB::transaction(function () use ($version): PlatformVersion {
            PlatformVersion::query()->where('is_current', true)->update([
                'is_current' => false,
                'status' => PlatformVersionStatus::Deprecated,
            ]);

            $version->update([
                'status' => PlatformVersionStatus::Released,
                'is_current' => true,
                'released_at' => now(),
                'migration_status' => array_merge($version->migration_status ?? [], [
                    'ran_at' => now()->toIso8601String(),
                ]),
            ]);

            return $version->refresh();
        });
    }

    /**
     * Roll back from the current version to a target version.
     *
     * @param PlatformVersion $version The version being rolled back from
     * @param PlatformVersion $target The version to restore as current
     * @return PlatformVersion
     */
    public function rollbackVersion(PlatformVersion $version, PlatformVersion $target): PlatformVersion
    {
        return DB::transaction(function () use ($version, $target): PlatformVersion {
            $version->update([
                'status' => PlatformVersionStatus::RolledBack,
                'is_current' => false,
                'rolled_back_at' => now(),
            ]);

            $target->update([
                'status' => PlatformVersionStatus::Released,
                'is_current' => true,
                'rolled_back_at' => null,
            ]);

            return $target->refresh();
        });
    }

    /**
     * Retrieve the currently active platform version.
     *
     * @return PlatformVersion|null
     */
    public function currentVersion(): ?PlatformVersion
    {
        return PlatformVersion::query()->where('is_current', true)->first();
    }
}
