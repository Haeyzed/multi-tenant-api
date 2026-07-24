<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Enums\Tenant\UserStatus;
use App\Mail\Tenant\WelcomeTenantOwner;
use App\Models\Central\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\TenantSettingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

/**
 * Service responsible for provisioning tenant owner accounts.
 *
 * Creates or updates the tenant owner user inside the tenant database,
 * manages invitation tokens, sends welcome mail, and tracks invite metadata
 * on the central tenant record.
 */
final class TenantOwnerProvisioningService
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
        private readonly TenantSettingService $settingService,
    ) {}

    /**
     * Provision an active tenant owner with a known password.
     *
     * Demo/backfill helper that skips invitation mail and marks the owner as
     * active with a verified email address.
     *
     *
     * @throws ValidationException|TenantCouldNotBeIdentifiedById
     */
    public function provisionWithPassword(Tenant $tenant, string $password, ?string $ownerName = null): TenantUser
    {
        if (! filled($tenant->email)) {
            throw ValidationException::withMessages([
                'email' => ['Tenant email is required to provision an owner.'],
            ]);
        }

        tenancy()->initialize($tenant);

        try {
            /** @var TenantUser $user */
            $user = TenantUser::query()->updateOrCreate(
                ['email' => $tenant->email],
                [
                    'name' => $ownerName ?? $tenant->name.' Owner',
                    'password' => $password,
                    'is_owner' => true,
                    'status' => UserStatus::Active,
                    'invitation_token' => null,
                    'invitation_expires_at' => null,
                    'email_verified_at' => now(),
                ],
            );

            TenantUser::query()
                ->where('id', '!=', $user->id)
                ->where('is_owner', true)
                ->update(['is_owner' => false]);

            $this->settingService->seedDefaults((string) ($tenant->name ?? 'Store'));
        } finally {
            tenancy()->end();
        }

        $metadata = $tenant->metadata ?? [];
        $metadata['owner_invite'] = [
            'email' => $tenant->email,
            'sent_at' => null,
            'expires_at' => null,
            'accepted_at' => now()->toIso8601String(),
            'provisioned_with_password' => true,
        ];
        $tenant->update(['metadata' => $metadata]);

        return $user;
    }

    /**
     * Resend the tenant owner invitation email.
     *
     * Re-provisions the owner invitation and sends a new welcome mail.
     *
     * @return array{user: TenantUser, plain_token: string|null, setup_url: string|null}
     *
     * @throws ValidationException|TenantCouldNotBeIdentifiedById
     */
    public function resendInvite(Tenant $tenant): array
    {
        return $this->provision($tenant);
    }

    /**
     * Provision a tenant owner via invitation token and optional welcome mail.
     *
     * Initializes tenant context, creates or updates the owner user with an
     * invitation token, ensures a single owner flag, and records invite metadata.
     *
     * @return array{user: TenantUser, plain_token: string|null, setup_url: string|null}
     *
     * @throws ValidationException|TenantCouldNotBeIdentifiedById
     */
    public function provision(Tenant $tenant, bool $sendMail = true, ?string $ownerName = null): array
    {
        if (! filled($tenant->email)) {
            throw ValidationException::withMessages([
                'email' => ['Tenant email is required to provision an owner invitation.'],
            ]);
        }

        $tenant->loadMissing('domains');

        if ($tenant->domains->isEmpty()) {
            throw ValidationException::withMessages([
                'domain' => ['A primary domain is required before inviting the tenant owner.'],
            ]);
        }

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours($this->tenantSettings->ownerInviteTtlHours());

        tenancy()->initialize($tenant);

        try {
            /** @var TenantUser $user */
            $user = TenantUser::query()->updateOrCreate(
                ['email' => $tenant->email],
                [
                    'name' => $ownerName ?? $tenant->name.' Owner',
                    'password' => null,
                    'is_owner' => true,
                    'status' => UserStatus::Invited,
                    'invitation_token' => hash('sha256', $plainToken),
                    'invitation_expires_at' => $expiresAt,
                    'email_verified_at' => null,
                ],
            );

            // Ensure only one owner flag.
            TenantUser::query()
                ->where('id', '!=', $user->id)
                ->where('is_owner', true)
                ->update(['is_owner' => false]);

            $this->settingService->seedDefaults((string) ($tenant->name ?? 'Store'));
        } finally {
            tenancy()->end();
        }

        $setupUrl = $this->setupPasswordUrl($tenant, $plainToken);

        $metadata = $tenant->metadata ?? [];
        $metadata['owner_invite'] = [
            'email' => $tenant->email,
            'sent_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'accepted_at' => null,
        ];
        $tenant->update(['metadata' => $metadata]);

        if ($sendMail) {
            Mail::to($tenant->email)->send(new WelcomeTenantOwner(
                tenantName: $tenant->name,
                primaryDomain: $this->primaryDomain($tenant),
                setupUrl: $setupUrl,
                expiresAt: $expiresAt,
            ));
        }

        return [
            'user' => $user,
            'plain_token' => $plainToken,
            'setup_url' => $setupUrl,
        ];
    }

    /**
     * Build the password setup URL for a tenant owner invitation.
     *
     * Substitutes the tenant primary domain and plain token into the
     * configured setup URL template.
     */
    public function setupPasswordUrl(Tenant $tenant, string $plainToken): string
    {
        $domain = $this->primaryDomain($tenant);
        $template = (string) config('app.tenant_setup_password_url', 'http://{domain}/setup-password?token={token}');

        return str_replace(
            ['{domain}', '{token}'],
            [$domain, urlencode($plainToken)],
            $template,
        );
    }

    /**
     * Resolve the primary domain hostname for a tenant.
     *
     * Falls back to the first domain when no primary is explicitly set.
     */
    public function primaryDomain(Tenant $tenant): string
    {
        $tenant->loadMissing('domains');

        $domain = $tenant->domains->firstWhere('is_primary', true)
            ?? $tenant->domains->first();

        return $domain?->domain ?? '';
    }

    /**
     * Mark the tenant owner invitation as accepted in tenant metadata.
     */
    public function markInviteAccepted(Tenant $tenant): void
    {
        $metadata = $tenant->metadata ?? [];
        $invite = $metadata['owner_invite'] ?? [];
        $invite['accepted_at'] = now()->toIso8601String();
        $metadata['owner_invite'] = $invite;
        $tenant->update(['metadata' => $metadata]);
    }
}
