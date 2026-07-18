<?php

declare(strict_types=1);

namespace App\Services\Tenant\Auth;

use App\Enums\Central\ImpersonationStatus;
use App\Enums\Tenant\UserStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Central\Tenants\ImpersonationService;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Service responsible for tenant-scoped authentication operations.
 *
 * Handles owner password setup, tenant user login/logout, and impersonation
 * token redemption within the active tenant context.
 */
final class TenantAuthService
{
    public function __construct(
        private readonly ImpersonationService           $impersonationService,
        private readonly TenantOwnerProvisioningService $ownerProvisioning,
    )
    {
    }

    /**
     * Complete tenant owner password setup from an invitation token.
     *
     * Validates the token, activates the owner account, marks the invite as
     * accepted, and returns a Sanctum API token.
     *
     * @param string $plainToken
     * @param string $password
     * @return array{token: string, user: TenantUser}
     *
     * @throws ValidationException
     */
    public function setupPassword(string $plainToken, string $password): array
    {
        $tenant = tenant();
        if (!$tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        if (!$tenant->canAccessPlatform()) {
            throw ValidationException::withMessages([
                'tenant' => ['This tenant cannot access the platform.'],
            ]);
        }

        $hashed = hash('sha256', $plainToken);

        /** @var TenantUser|null $user */
        $user = TenantUser::query()
            ->where('invitation_token', $hashed)
            ->where('is_owner', true)
            ->first();

        if ($user === null || $user->invitation_expires_at === null || $user->invitation_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation token is invalid or has expired.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'status' => UserStatus::Active,
            'invitation_token' => null,
            'invitation_expires_at' => null,
            'email_verified_at' => now(),
        ])->save();

        $this->ownerProvisioning->markInviteAccepted($tenant->fresh() ?? $tenant);

        $token = $user->createToken('owner-setup')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->fresh(),
        ];
    }

    /**
     * Authenticate a tenant user and issue a Sanctum API token.
     *
     * @param string $email
     * @param string $password
     * @param string|null $ip
     * @return array{token: string, user: TenantUser}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, ?string $ip = null): array
    {
        $tenant = tenant();
        if (!$tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        if (!$tenant->canAccessPlatform()) {
            throw ValidationException::withMessages([
                'tenant' => ['This tenant cannot access the platform.'],
            ]);
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::query()->where('email', $email)->first();

        if ($user === null || !filled($user->password) || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (!$user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => ['This account cannot authenticate.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        $token = $user->createToken('tenant-login')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->fresh(),
        ];
    }

    /**
     * Revoke the current Sanctum access token for a tenant user.
     *
     * @param TenantUser $user
     * @return void
     */
    public function logout(TenantUser $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        $token?->delete();
    }

    /**
     * Redeem a central impersonation token and issue an owner session token.
     *
     * @param string $plainToken
     * @return array{token: string, user: TenantUser}
     *
     * @throws ValidationException
     */
    public function redeemImpersonation(string $plainToken): array
    {
        $tenant = tenant();
        if (!$tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        $impersonation = $this->impersonationService->findActiveByPlainToken($plainToken);

        if ($impersonation === null || $impersonation->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired impersonation token.'],
            ]);
        }

        /** @var TenantUser|null $owner */
        $owner = TenantUser::query()->where('is_owner', true)->orderBy('id')->first();

        if ($owner === null) {
            throw ValidationException::withMessages([
                'token' => ['No tenant owner is available to impersonate.'],
            ]);
        }

        $impersonation->update([
            'status' => ImpersonationStatus::ACTIVE,
        ]);

        $token = $owner->createToken('impersonation:' . $impersonation->id, ['impersonation'])->plainTextToken;

        return [
            'token' => $token,
            'user' => $owner,
        ];
    }
}
