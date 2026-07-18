<?php

declare(strict_types=1);

namespace App\Services\Central\Users;

use App\Enums\Central\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

/**
 * Service responsible for central platform user management.
 *
 * Encapsulates CRUD, role/permission sync, status transitions, password
 * resets, avatar uploads, and activity queries so controllers remain thin.
 */
final class UserService
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    )
    {
    }

    /**
     * Create a new central platform user.
     *
     * Generates a random password when none is provided and optionally
     * assigns roles and permissions within a transaction.
     *
     * @param array{name: string, email: string, password?: string, phone?: string|null, timezone?: string, status?: string, roles?: list<string>, permissions?: list<string>} $data
     * @return User
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $password = $data['password'] ?? Str::password(16);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => $data['status'] ?? UserStatus::Active->value,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            if (!empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            if (!empty($data['permissions'])) {
                $user->syncPermissions($data['permissions']);
            }

            $this->permissionRegistrar->forgetCachedPermissions();

            return $user->load(['roles', 'permissions', 'media']);
        });
    }

    /**
     * Synchronize role assignments for a user.
     *
     * @param User $user
     * @param list<string> $roles
     * @return User
     */
    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);
        $this->permissionRegistrar->forgetCachedPermissions();

        return $user->fresh(['roles', 'permissions']);
    }

    /**
     * Synchronize direct permission assignments for a user.
     *
     * @param User $user
     * @param list<string> $permissions
     * @return User
     */
    public function syncPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($permissions);
        $this->permissionRegistrar->forgetCachedPermissions();

        return $user->fresh(['roles', 'permissions']);
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param User $user
     * @return User
     */
    public function restore(User $user): User
    {
        $user->restore();

        return $user->fresh(['roles', 'permissions', 'media']);
    }

    /**
     * Permanently delete a user and revoke all tokens.
     *
     * @param User $actor
     * @param User $user
     * @return void
     *
     * @throws ValidationException
     */
    public function forceDelete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot permanently delete your own account.'],
            ]);
        }

        $user->tokens()->delete();
        $user->forceDelete();
    }

    /**
     * Soft-delete a user and revoke all tokens.
     *
     * @param User $actor
     * @param User $user
     * @return void
     *
     * @throws ValidationException
     */
    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->tokens()->delete();
        $user->delete();
    }

    /**
     * Soft-delete multiple users and revoke their tokens.
     *
     * @param User $actor
     * @param list<int> $ids
     * @return int
     *
     * @throws ValidationException
     */
    public function deleteMany(User $actor, array $ids): int
    {
        $users = User::query()->whereIn('id', $ids)->get();

        $users->each(fn(User $user) => $this->delete($actor, $user));

        return $users->count();
    }

    /**
     * Suspend multiple users and revoke their tokens.
     *
     * @param User $actor
     * @param list<int> $ids
     * @return int
     *
     * @throws ValidationException
     */
    public function suspendMany(User $actor, array $ids): int
    {
        return $this->updateStatusMany($actor, $ids, UserStatus::Suspended);
    }

    /**
     * Update status for multiple users and revoke tokens when needed.
     *
     * @param User $actor
     * @param list<int> $ids
     * @param UserStatus $status
     * @return int
     *
     * @throws ValidationException
     */
    public function updateStatusMany(User $actor, array $ids, UserStatus $status): int
    {
        $users = User::query()->whereIn('id', $ids)->get();

        $users->each(fn(User $user) => $this->updateStatus($actor, $user, $status));

        return $users->count();
    }

    /**
     * Update a user's account status.
     *
     * Revokes tokens when the new status prevents authentication and blocks
     * self-deactivation.
     *
     * @param User $actor
     * @param User $user
     * @param UserStatus $status
     * @return User
     *
     * @throws ValidationException
     */
    public function updateStatus(User $actor, User $user, UserStatus $status): User
    {
        if ($actor->is($user) && $status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'status' => ['You cannot deactivate your own account.'],
            ]);
        }

        $user->update(['status' => $status]);

        if (!$status->canAuthenticate()) {
            $user->tokens()->delete();
        }

        return $user->fresh(['roles', 'permissions', 'media']);
    }

    /**
     * Update an existing central platform user.
     *
     * Optionally syncs roles and permissions when present in the payload.
     *
     * @param User $user
     * @param array{name?: string, email?: string, phone?: string|null, timezone?: string, status?: string, roles?: list<string>, permissions?: list<string>} $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(collect($data)->only([
                'name', 'email', 'phone', 'timezone', 'status',
            ])->all());
            $user->save();

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles'] ?? []);
            }

            if (array_key_exists('permissions', $data)) {
                $user->syncPermissions($data['permissions'] ?? []);
            }

            $this->permissionRegistrar->forgetCachedPermissions();

            return $user->fresh(['roles', 'permissions', 'media']);
        });
    }

    /**
     * Activate multiple users.
     *
     * @param User $actor
     * @param list<int> $ids
     * @return int
     *
     * @throws ValidationException
     */
    public function activateMany(User $actor, array $ids): int
    {
        return $this->updateStatusMany($actor, $ids, UserStatus::Active);
    }

    /**
     * Reset a user's password and revoke existing tokens.
     *
     * @param User $user
     * @param string|null $password
     * @return string  The plain-text password that was set
     */
    public function resetPassword(User $user, ?string $password = null): string
    {
        $plain = $password ?? Str::password(16);
        $user->update(['password' => $plain]);
        $user->tokens()->delete();

        return $plain;
    }

    /**
     * Upload and attach an avatar for a user.
     *
     * @param User $user
     * @param UploadedFile $file
     * @return User
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $user->addMedia($file)->toMediaCollection('avatar');

        return $user->fresh(['roles', 'permissions', 'media']);
    }

    /**
     * Build a security summary for a user account.
     *
     * @param User $user
     * @return array{two_factor_enabled: bool, last_login_at: Carbon|null, last_login_ip: string|null, token_count: int, email_verified: bool}
     */
    public function securitySummary(User $user): array
    {
        return [
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
            'token_count' => $user->tokens()->count(),
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    /**
     * Paginate activity log entries caused by or performed on a user.
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator<int, Activity>
     */
    public function activities(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Activity::query()
            ->where(function ($query) use ($user): void {
                $query->where(function ($q) use ($user): void {
                    $q->where('subject_type', User::class)
                        ->where('subject_id', $user->id);
                })->orWhere(function ($q) use ($user): void {
                    $q->where('causer_type', User::class)
                        ->where('causer_id', $user->id);
                });
            })
            ->latest()
            ->paginate(min($perPage, 100));
    }

    /**
     * Paginate central platform users with optional filters.
     *
     * @param array{search?: string, status?: string, role?: string, trashed?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return User::query()
            ->with(['roles', 'permissions', 'media'])
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['role'] ?? null,
                fn($query, string $role) => $query->role($role)
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
     * Aggregate overview statistics for the users index page.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     inactive: int,
     *     suspended: int,
     *     with_two_factor: int,
     *     trashed: int,
     *     by_status: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = User::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'active' => (int)($byStatus[UserStatus::Active->value] ?? 0),
            'inactive' => (int)($byStatus[UserStatus::Inactive->value] ?? 0),
            'suspended' => (int)($byStatus[UserStatus::Suspended->value] ?? 0),
            'with_two_factor' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
            'trashed' => User::onlyTrashed()->count(),
            'by_status' => $byStatus,
        ];
    }
}
