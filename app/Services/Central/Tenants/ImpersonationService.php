<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Enums\Central\ImpersonationReason;
use App\Enums\Central\ImpersonationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantImpersonation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for central tenant impersonation sessions.
 *
 * Manages starting, revoking, and resolving active impersonation tokens
 * so platform staff can access tenant contexts with audit logging.
 */
final class ImpersonationService
{
    /**
     * Start a new impersonation session for a tenant.
     *
     * Revokes any existing active sessions for the actor, creates a hashed
     * token with expiry, logs the activity, and returns the impersonation URL.
     *
     * @param Tenant $tenant
     * @param User $actor
     * @param ImpersonationReason $reason
     * @param string|null $reasonNotes
     * @param string|null $ip
     * @param string|null $userAgent
     * @param int $ttlMinutes
     * @return array{impersonation: TenantImpersonation, url: string, token: string}
     *
     * @throws ValidationException
     */
    public function start(
        Tenant              $tenant,
        User                $actor,
        ImpersonationReason $reason,
        ?string             $reasonNotes = null,
        ?string             $ip = null,
        ?string             $userAgent = null,
        int                 $ttlMinutes = 60,
    ): array
    {
        if (!$tenant->canAccessPlatform()) {
            throw ValidationException::withMessages([
                'tenant' => ['Cannot impersonate a tenant that cannot access the platform.'],
            ]);
        }

        $tenant->impersonations()
            ->where('user_id', $actor->id)
            ->where('status', ImpersonationStatus::ACTIVE)
            ->update([
                'status' => ImpersonationStatus::REVOKED,
                'ended_at' => now(),
            ]);

        $token = Str::random(64);

        $impersonation = $tenant->impersonations()->create([
            'user_id' => $actor->id,
            'token' => hash('sha256', $token),
            'reason' => $reason,
            'reason_notes' => $reasonNotes,
            'status' => ImpersonationStatus::ACTIVE,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($tenant)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'impersonation_id' => $impersonation->id,
                'reason' => $reason->value,
            ])
            ->event('impersonation_started')
            ->log('Tenant impersonation started');

        $base = rtrim((string)config('app.tenant_impersonation_url', config('app.url')), '/');

        return [
            'impersonation' => $impersonation->load(['user', 'tenant']),
            'token' => $token,
            'url' => $base . '/impersonate?token=' . $token . '&tenant=' . $tenant->id,
        ];
    }

    /**
     * Revoke an active impersonation session.
     *
     * @param TenantImpersonation $impersonation
     * @param User|null $actor
     * @return TenantImpersonation
     *
     * @throws ValidationException
     */
    public function revoke(TenantImpersonation $impersonation, ?User $actor = null): TenantImpersonation
    {
        if ($impersonation->status !== ImpersonationStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'impersonation' => ['Only active impersonation sessions can be revoked.'],
            ]);
        }

        $impersonation->update([
            'status' => ImpersonationStatus::REVOKED,
            'ended_at' => now(),
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($impersonation->tenant)
            ->withProperties([
                'tenant_id' => $impersonation->tenant_id,
                'impersonation_id' => $impersonation->id,
            ])
            ->event('impersonation_revoked')
            ->log('Tenant impersonation revoked');

        return $impersonation->fresh(['user', 'tenant']);
    }

    /**
     * Find an active impersonation session by its plain-text token.
     *
     * Expires stale sessions automatically when the expiry timestamp has passed.
     *
     * @param string $token
     * @return TenantImpersonation|null
     */
    public function findActiveByPlainToken(string $token): ?TenantImpersonation
    {
        /** @var TenantImpersonation|null $impersonation */
        $impersonation = TenantImpersonation::query()
            ->where('token', hash('sha256', $token))
            ->where('status', ImpersonationStatus::ACTIVE)
            ->first();

        if ($impersonation && $impersonation->expires_at?->isPast()) {
            $impersonation->update([
                'status' => ImpersonationStatus::EXPIRED,
                'ended_at' => now(),
            ]);

            return null;
        }

        return $impersonation;
    }
}
