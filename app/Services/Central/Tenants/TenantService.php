<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Enums\Central\TenantStatus;
use App\Jobs\Tenant\ProvisionTenantOwner;
use App\Models\Central\Tenant;
use App\Models\Central\TenantNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

/**
 * Service responsible for managing central tenant lifecycle operations.
 *
 * Encapsulates create, update, status transitions, notes, tags, metadata,
 * statistics, and health checks so controllers remain thin.
 */
final class TenantService
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
    )
    {
    }

    /**
     * Suspend the specified tenant.
     *
     * Marks the tenant as suspended, records the suspension timestamp and
     * optional reason, and clears any archived state.
     *
     * @param Tenant $tenant
     * @param string|null $reason
     * @return Tenant
     */
    public function suspend(Tenant $tenant, ?string $reason = null): Tenant
    {
        $tenant->update([
            'status' => TenantStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => $reason,
            'archived_at' => null,
        ]);

        return $tenant->fresh(['domains']);
    }

    /**
     * Update an existing tenant.
     *
     * Applies mutable attributes while ensuring slug uniqueness when the
     * slug changes. Immutable identifiers are preserved.
     *
     * @param Tenant $tenant
     * @param array{
     *     name?: string,
     *     slug?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     tags?: list<string>,
     *     metadata?: array<string, mixed>,
     *     trial_ends_at?: string|null
     * } $data
     * @return Tenant
     *
     * @throws ValidationException
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        if (isset($data['slug']) && $data['slug'] !== $tenant->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $tenant->id);
        }

        $tenant->fill(collect($data)->only([
            'name', 'slug', 'email', 'phone', 'tags', 'metadata', 'trial_ends_at',
        ])->all());
        $tenant->save();

        return $tenant->fresh(['domains'])->loadCount(['domains', 'notes']);
    }

    /**
     * Generate a unique tenant slug.
     *
     * Appends an incrementing suffix when the base slug is already taken,
     * optionally ignoring a tenant ID during updates.
     *
     * @param string $slug
     * @param string|null $ignoreId
     * @return string
     *
     * @throws ValidationException
     */
    private function uniqueSlug(string $slug, ?string $ignoreId = null): string
    {
        $base = Str::slug($slug);
        $candidate = $base;
        $i = 1;

        while (
        Tenant::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        if ($candidate === '') {
            throw ValidationException::withMessages([
                'slug' => ['A valid slug is required.'],
            ]);
        }

        return $candidate;
    }

    /**
     * Activate the specified tenant.
     *
     * Clears suspension and archive metadata and sets status to active.
     *
     * @param Tenant $tenant
     * @return Tenant
     */
    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update([
            'status' => TenantStatus::ACTIVE,
            'suspended_at' => null,
            'suspended_reason' => null,
            'archived_at' => null,
        ]);

        return $tenant->fresh(['domains']);
    }

    /**
     * Archive the specified tenant.
     *
     * Marks the tenant as archived and records the archive timestamp.
     *
     * @param Tenant $tenant
     * @return Tenant
     */
    public function archive(Tenant $tenant): Tenant
    {
        $tenant->update([
            'status' => TenantStatus::ARCHIVED,
            'archived_at' => now(),
        ]);

        return $tenant->fresh(['domains']);
    }

    /**
     * Soft delete multiple tenants by ID.
     *
     * @param list<string> $ids
     * @return int
     */
    public function deleteMany(array $ids): int
    {
        $tenants = Tenant::query()->whereIn('id', $ids)->get();

        $tenants->each(fn(Tenant $tenant) => $this->delete($tenant));

        return $tenants->count();
    }

    /**
     * Soft delete the specified tenant.
     *
     * @param Tenant $tenant
     * @return void
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    /**
     * Suspend multiple tenants by ID.
     *
     * @param list<string> $ids
     * @param string|null $reason
     * @return int
     */
    public function suspendMany(array $ids, ?string $reason = null): int
    {
        return Tenant::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => TenantStatus::SUSPENDED,
                'suspended_at' => now(),
                'suspended_reason' => $reason,
                'archived_at' => null,
            ]);
    }

    /**
     * Activate multiple tenants by ID.
     *
     * @param list<string> $ids
     * @return int
     */
    public function activateMany(array $ids): int
    {
        return Tenant::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => TenantStatus::ACTIVE,
                'suspended_at' => null,
                'suspended_reason' => null,
                'archived_at' => null,
            ]);
    }

    /**
     * Restore a soft-deleted tenant.
     *
     * When the restored tenant was archived, status is returned to active
     * and the archive timestamp is cleared.
     *
     * @param Tenant $tenant
     * @return Tenant
     */
    public function restore(Tenant $tenant): Tenant
    {
        $tenant->restore();

        if ($tenant->status === TenantStatus::ARCHIVED) {
            $tenant->update([
                'status' => TenantStatus::ACTIVE,
                'archived_at' => null,
            ]);
        }

        return $tenant->fresh(['domains']);
    }

    /**
     * Replace the tenant's tags with the given unique list.
     *
     * @param Tenant $tenant
     * @param list<string> $tags
     * @return Tenant
     */
    public function syncTags(Tenant $tenant, array $tags): Tenant
    {
        $tenant->update(['tags' => array_values(array_unique($tags))]);

        return $tenant->fresh(['domains']);
    }

    /**
     * Merge metadata into the tenant's existing metadata payload.
     *
     * New keys overwrite existing keys with the same name.
     *
     * @param Tenant $tenant
     * @param array<string, mixed> $metadata
     * @return Tenant
     */
    public function mergeMetadata(Tenant $tenant, array $metadata): Tenant
    {
        $tenant->update([
            'metadata' => array_merge($tenant->metadata ?? [], $metadata),
        ]);

        return $tenant->fresh(['domains']);
    }

    /**
     * Add an internal or external note to the tenant.
     *
     * @param Tenant $tenant
     * @param User $author
     * @param string $body
     * @param bool $isInternal
     * @return TenantNote
     */
    public function addNote(Tenant $tenant, User $author, string $body, bool $isInternal = true): TenantNote
    {
        return $tenant->notes()->create([
            'user_id' => $author->id,
            'body' => $body,
            'is_internal' => $isInternal,
        ])->load('author');
    }

    /**
     * Create a new tenant and optional primary domain.
     *
     * Defaults status to trial when an email is provided, otherwise pending.
     * When email is present and no domain is supplied, a primary domain is
     * derived from the slug and configured tenant base domain.
     *
     * Do not wrap this method in DB::transaction(); TenantCreated runs
     * CreateDatabase (DDL), which implicitly commits on MySQL.
     *
     * After the primary domain is attached, the owner is provisioned:
     * password when `owner_password` is present, otherwise invitation email
     * (invite path skipped during automated tests).
     *
     * @param array{
     *     name: string,
     *     slug?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     status?: string|TenantStatus,
     *     tags?: list<string>,
     *     metadata?: array<string, mixed>,
     *     domain?: string,
     *     trial_ends_at?: string|null,
     *     owner_password?: string|null,
     *     owner_name?: string|null
     * } $data
     * @return Tenant
     *
     * @throws ValidationException
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function create(array $data): Tenant
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $slug = $this->uniqueSlug($slug);
        $email = $data['email'] ?? null;
        $ownerPassword = $data['owner_password'] ?? null;
        $ownerName = $data['owner_name'] ?? null;
        $domain = filled($data['domain'] ?? null)
            ? Str::lower(trim((string)$data['domain']))
            : null;

        if (filled($domain) && !$this->tenantSettings->allowCustomDomains()) {
            $baseDomain = Str::lower(trim((string)config('app.tenant_base_domain', 'localhost')));
            $isPlatformDomain = $domain === $baseDomain || Str::endsWith($domain, '.' . $baseDomain);

            if (!$isPlatformDomain) {
                throw ValidationException::withMessages([
                    'domain' => ['Custom tenant domains are currently disabled by the platform.'],
                ]);
            }
        }

        $defaultStatus = filled($email)
            ? TenantStatus::TRIAL
            : TenantStatus::PENDING;

        $status = $data['status'] ?? $defaultStatus;
        if ($status instanceof TenantStatus) {
            $status = $status->value;
        }

        $trialEndsAt = array_key_exists('trial_ends_at', $data)
            ? $data['trial_ends_at']
            : (filled($email)
                ? now()->addDays($this->tenantSettings->defaultTrialDays())->toDateTimeString()
                : null);

        $this->ensureSlugDatabaseIsAvailable($slug);

        try {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'status' => $status,
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'trial_ends_at' => $trialEndsAt,
            ]);
        } catch (TenantDatabaseAlreadyExistsException) {
            $this->cleanupFailedTenantCreate($slug);

            try {
                $tenant = Tenant::create([
                    'name' => $data['name'],
                    'slug' => $slug,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'status' => $status,
                    'tags' => $data['tags'] ?? [],
                    'metadata' => $data['metadata'] ?? [],
                    'trial_ends_at' => $trialEndsAt,
                ]);
            } catch (TenantDatabaseAlreadyExistsException) {
                throw ValidationException::withMessages([
                    'slug' => ["The organization slug [{$slug}] is not available because its database already exists."],
                    'name' => ["The organization slug [{$slug}] is not available because its database already exists."],
                ]);
            }
        }

        if (
            !filled($domain)
            && filled($email)
            && $this->tenantSettings->autoGenerateDomain()
        ) {
            $base = (string)config('app.tenant_base_domain', 'localhost');
            $domain = $slug . '.' . $base;
        }

        if (filled($domain)) {
            $tenant->domains()->create([
                'domain' => Str::lower((string)$domain),
                'type' => DomainType::PRIMARY,
                'status' => DomainStatus::ACTIVE,
                'is_primary' => true,
                'force_https' => $this->tenantSettings->defaultForceHttps(),
            ]);
        }

        $tenant = $tenant->load(['domains'])->loadCount(['domains', 'notes']);

        $this->provisionOwnerAfterCreate($tenant, $ownerPassword, $ownerName);

        return $tenant;
    }

    /**
     * Ensure no orphan tenant database blocks signup for this slug.
     */
    private function ensureSlugDatabaseIsAvailable(string $slug): void
    {
        if (!$this->tenantDatabaseExists($slug)) {
            return;
        }

        $owned = Tenant::withTrashed()->where('slug', $slug)->exists();

        if ($owned) {
            throw ValidationException::withMessages([
                'slug' => ["The organization slug [{$slug}] is already taken."],
                'name' => ["The organization slug [{$slug}] is already taken."],
            ]);
        }

        $this->dropOrphanTenantDatabase($slug);
    }

    private function tenantDatabaseExists(string $slug): bool
    {
        $probe = new Tenant([
            'id' => (string)Str::uuid(),
            'slug' => $slug,
        ]);

        return $probe->database()->manager()->databaseExists(
            $this->databaseNameForSlug($slug),
        );
    }

    /**
     * Database name used for a tenant slug (slug-based naming from AppServiceProvider).
     */
    public function databaseNameForSlug(string $slug): string
    {
        return config('tenancy.database.prefix', 'tenant-')
            . $slug
            . config('tenancy.database.suffix', '');
    }

    /**
     * Drop a leftover tenant database when no tenant row owns the slug.
     */
    private function dropOrphanTenantDatabase(string $slug): void
    {
        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            return;
        }

        if (!$this->tenantDatabaseExists($slug)) {
            return;
        }

        $probe = new Tenant([
            'id' => (string)Str::uuid(),
            'slug' => $slug,
        ]);

        $probe->database()->manager()->deleteDatabase($probe);
    }

    /**
     * Remove a partial tenant row / orphan DB left when CreateDatabase fails.
     */
    private function cleanupFailedTenantCreate(string $slug): void
    {
        $partial = Tenant::withTrashed()
            ->where('slug', $slug)
            ->latest('created_at')
            ->first();

        if ($partial !== null) {
            $partial->domains()->delete();

            // TenantDeleted drops the slug-named database when not running unit tests.
            $partial->forceDelete();
        }

        $this->dropOrphanTenantDatabase($slug);
    }

    /**
     * Provision the tenant owner after the primary domain exists.
     *
     * Password provisioning is used for self-serve signup. Invitation mail
     * remains the default for Central admin creates (skipped in tests).
     * @throws TenantCouldNotBeIdentifiedById
     */
    private function provisionOwnerAfterCreate(
        Tenant  $tenant,
        ?string $ownerPassword = null,
        ?string $ownerName = null,
    ): void
    {
        if (!filled($tenant->email) || $tenant->domains->isEmpty()) {
            return;
        }

        if (filled($ownerPassword)) {
            $this->ensureTenantDatabaseForProvisioning($tenant);

            app(TenantOwnerProvisioningService::class)->provisionWithPassword(
                $tenant,
                $ownerPassword,
                $ownerName,
            );

            return;
        }

        if (app()->runningUnitTests()) {
            return;
        }

        (new ProvisionTenantOwner($tenant))->handle(
            app(TenantOwnerProvisioningService::class),
        );
    }

    /**
     * Create and migrate the tenant DB when TenantCreated jobs are disabled (tests).
     */
    private function ensureTenantDatabaseForProvisioning(Tenant $tenant): void
    {
        if (!app()->runningUnitTests()) {
            return;
        }

        (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
        (new MigrateDatabase($tenant))->handle();

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /**
     * Retrieve a paginated list of notes for the tenant.
     *
     * @param Tenant $tenant
     * @param int $perPage
     * @return LengthAwarePaginator<int, TenantNote>
     */
    public function paginateNotes(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return $tenant->notes()
            ->with('author')
            ->latest()
            ->paginate(min($perPage, 100));
    }

    /**
     * Retrieve a paginated list of tenants.
     *
     * Applies search, status, tag, and trashed filters before returning
     * the paginated result with domains and counts.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     tag?: string,
     *     trashed?: string,
     *     per_page?: int
     * } $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Tenant::query()
            ->with(['domains'])
            ->withCount(['domains', 'notes'])
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('slug', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['tag'] ?? null,
                fn($query, string $tag) => $query->whereJsonContains('tags', $tag)
            )
            ->when(
                ($filters['trashed'] ?? null) === 'only',
                fn($query) => $query->onlyTrashed()
            )
            ->when(
                ($filters['trashed'] ?? null) === 'with',
                fn($query) => $query->withTrashed()
            )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(?string $search = null): array
    {
        return Tenant::query()
            ->when(
                filled($search),
                fn($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Tenant $tenant): array => [
                'value' => (string)$tenant->getKey(),
                'label' => (string)$tenant->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Aggregate overview statistics for the tenants index page.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     trial: int,
     *     suspended: int,
     *     pending: int,
     *     archived: int,
     *     trashed: int,
     *     by_status: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = Tenant::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'active' => (int)($byStatus[TenantStatus::ACTIVE->value] ?? 0),
            'trial' => (int)($byStatus[TenantStatus::TRIAL->value] ?? 0),
            'suspended' => (int)($byStatus[TenantStatus::SUSPENDED->value] ?? 0),
            'pending' => (int)($byStatus[TenantStatus::PENDING->value] ?? 0),
            'archived' => (int)($byStatus[TenantStatus::ARCHIVED->value] ?? 0),
            'trashed' => Tenant::onlyTrashed()->count(),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Build aggregate statistics for the tenant.
     *
     * @param Tenant $tenant
     * @return array{
     *     domains_count: int,
     *     primary_domain: string|null,
     *     notes_count: int,
     *     impersonations_count: int,
     *     active_impersonations: int,
     *     status: string|null,
     *     can_access: bool,
     *     trial_ends_at: Carbon|null,
     *     created_at: Carbon|null
     * }
     */
    public function statistics(Tenant $tenant): array
    {
        return [
            'domains_count' => $tenant->domains()->count(),
            'primary_domain' => $tenant->domains()->where('is_primary', true)->value('domain'),
            'notes_count' => $tenant->notes()->count(),
            'impersonations_count' => $tenant->impersonations()->count(),
            'active_impersonations' => $tenant->impersonations()->where('status', 'active')->count(),
            'status' => $tenant->status?->value,
            'can_access' => $tenant->canAccessPlatform(),
            'trial_ends_at' => $tenant->trial_ends_at,
            'created_at' => $tenant->created_at,
        ];
    }

    /**
     * Evaluate platform health checks for the tenant.
     *
     * A tenant is considered healthy when it can access the platform and
     * has a primary domain configured.
     *
     * @param Tenant $tenant
     * @return array{
     *     healthy: bool,
     *     score: int,
     *     checks: array{
     *         status_ok: bool,
     *         has_primary_domain: bool,
     *         primary_domain_active: bool,
     *         has_verified_domain: bool,
     *         ssl_configured: bool,
     *         database_name: string
     *     }
     * }
     */
    public function health(Tenant $tenant): array
    {
        $primary = $tenant->domains()->where('is_primary', true)->first();
        $verifiedDomains = $tenant->domains()->whereNotNull('dns_verified_at')->count();
        $sslActive = $tenant->domains()
            ->where(function ($query): void {
                $query->where('ssl_status', 'ssl_active')
                    ->orWhere('ssl_enabled', true);
            })
            ->count();

        $checks = [
            'status_ok' => $tenant->canAccessPlatform(),
            'has_primary_domain' => $primary !== null,
            'primary_domain_active' => $primary?->status === DomainStatus::ACTIVE,
            'has_verified_domain' => $verifiedDomains > 0,
            'ssl_configured' => $sslActive > 0,
            'database_name' => config('tenancy.database.prefix', 'tenant_') . $tenant->slug . config('tenancy.database.suffix', ''),
        ];

        $healthy = $checks['status_ok'] && $checks['has_primary_domain'];

        return [
            'healthy' => $healthy,
            'score' => collect($checks)->except('database_name')->filter()->count(),
            'checks' => $checks,
        ];
    }

    /**
     * Retrieve a paginated activity feed for the tenant.
     *
     * Includes activities performed on the tenant model and activities that
     * reference the tenant ID in their properties payload.
     *
     * @param Tenant $tenant
     * @param int $perPage
     * @return LengthAwarePaginator<int, Activity>
     */
    public function activities(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return Activity::query()
            ->where(function ($query) use ($tenant): void {
                $query->where(function ($q) use ($tenant): void {
                    $q->where('subject_type', Tenant::class)
                        ->where('subject_id', $tenant->id);
                })->orWhere(function ($q) use ($tenant): void {
                    $q->where('properties->tenant_id', $tenant->id);
                });
            })
            ->latest()
            ->paginate(min($perPage, 100));
    }
}
