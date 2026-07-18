<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for tenant domain lifecycle operations.
 *
 * Encapsulates domain creation, updates, primary assignment, DNS verification,
 * SSL toggling, and redirect configuration so controllers remain thin.
 */
final class DomainService
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
    )
    {
    }

    /**
     * Create a new domain for the specified tenant.
     *
     * Ensures domain uniqueness, handles primary domain assignment, and
     * generates a DNS verification token for pending domains.
     *
     * @param Tenant $tenant
     * @param array{domain: string, type?: string, is_primary?: bool, is_redirect?: bool, redirect_to?: string|null, force_https?: bool} $data
     * @return Domain
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function create(Tenant $tenant, array $data): Domain
    {
        return DB::transaction(function () use ($tenant, $data): Domain {
            $domainName = Str::lower($data['domain']);

            if (Domain::query()->where('domain', $domainName)->exists()) {
                throw ValidationException::withMessages([
                    'domain' => ['This domain is already in use.'],
                ]);
            }

            $isPrimary = (bool)($data['is_primary'] ?? false);

            if ($isPrimary) {
                $tenant->domains()->update(['is_primary' => false]);
            }

            if ($tenant->domains()->count() === 0) {
                $isPrimary = true;
            }

            $type = $data['type'] ?? ($isPrimary
                ? DomainType::PRIMARY->value
                : DomainType::CUSTOM->value);
            $typeValue = $type instanceof DomainType ? $type->value : (string)$type;

            if ($typeValue === DomainType::CUSTOM->value && !$this->tenantSettings->allowCustomDomains()) {
                throw ValidationException::withMessages([
                    'domain' => ['Custom tenant domains are currently disabled by the platform.'],
                ]);
            }

            return $tenant->domains()->create([
                'domain' => $domainName,
                'type' => $typeValue,
                'status' => DomainStatus::PENDING,
                'is_primary' => $isPrimary,
                'is_redirect' => (bool)($data['is_redirect'] ?? false),
                'redirect_to' => $data['redirect_to'] ?? null,
                'force_https' => $data['force_https'] ?? $this->tenantSettings->defaultForceHttps(),
                'dns_verification_token' => Str::random(40),
            ]);
        });
    }

    /**
     * Update an existing tenant domain.
     *
     * When the hostname changes, resets DNS verification state and regenerates
     * the verification token.
     *
     * @param Domain $domain
     * @param array{domain?: string, type?: string, is_redirect?: bool, redirect_to?: string|null, force_https?: bool, status?: string} $data
     * @return Domain
     *
     * @throws ValidationException
     */
    public function update(Domain $domain, array $data): Domain
    {
        if (isset($data['domain']) && Str::lower($data['domain']) !== $domain->domain) {
            $domainName = Str::lower($data['domain']);

            if (Domain::query()->where('domain', $domainName)->whereKeyNot($domain->id)->exists()) {
                throw ValidationException::withMessages([
                    'domain' => ['This domain is already in use.'],
                ]);
            }

            $data['domain'] = $domainName;
            $data['dns_verified_at'] = null;
            $data['dns_verification_token'] = Str::random(40);
            $data['status'] = DomainStatus::VERIFICATION_PENDING->value;
        }

        $domain->fill(collect($data)->only([
            'domain', 'type', 'is_redirect', 'redirect_to', 'force_https', 'status',
            'dns_verified_at', 'dns_verification_token',
        ])->all());
        $domain->save();

        return $domain->fresh();
    }

    /**
     * Delete a tenant domain.
     *
     * Prevents deletion of the primary domain when other domains exist.
     *
     * @param Domain $domain
     * @return void
     *
     * @throws ValidationException
     */
    public function delete(Domain $domain): void
    {
        if ($domain->is_primary && $domain->tenant->domains()->count() > 1) {
            throw ValidationException::withMessages([
                'domain' => ['Assign another primary domain before deleting this one.'],
            ]);
        }

        $domain->delete();
    }

    /**
     * Promote a domain to the tenant's primary domain.
     *
     * Demotes all other domains to custom type within a transaction.
     *
     * @param Domain $domain
     * @return Domain
     *
     * @throws Throwable
     */
    public function makePrimary(Domain $domain): Domain
    {
        return DB::transaction(function () use ($domain): Domain {
            $domain->tenant->domains()->update([
                'is_primary' => false,
                'type' => DomainType::CUSTOM,
            ]);

            $domain->update([
                'is_primary' => true,
                'type' => DomainType::PRIMARY,
                'status' => $domain->status === DomainStatus::PENDING
                    ? DomainStatus::ACTIVE
                    : $domain->status,
            ]);

            return $domain->fresh();
        });
    }

    /**
     * Verify DNS ownership of a domain via TXT record lookup.
     *
     * Regenerates the verification token when missing and marks the domain
     * active on success or verification-failed on mismatch.
     *
     * @param Domain $domain
     * @return Domain
     *
     * @throws ValidationException
     */
    public function verifyDns(Domain $domain): Domain
    {
        $token = $domain->dns_verification_token;

        if (blank($token)) {
            $domain = $this->regenerateDnsToken($domain);
            $token = $domain->dns_verification_token;
        }

        $verified = $this->dnsTokenIsPresent((string)$domain->domain, (string)$token);

        if (!$verified) {
            $domain->update(['status' => DomainStatus::VERIFICATION_FAILED]);

            throw ValidationException::withMessages([
                'domain' => ['DNS TXT record verification failed. Expected token was not found.'],
            ]);
        }

        $domain->update([
            'dns_verified_at' => now(),
            'status' => DomainStatus::ACTIVE,
        ]);

        return $domain->fresh();
    }

    /**
     * Regenerate the DNS verification token for a domain.
     *
     * Resets verification timestamp and sets status to verification pending.
     *
     * @param Domain $domain
     * @return Domain
     */
    public function regenerateDnsToken(Domain $domain): Domain
    {
        $domain->update([
            'dns_verification_token' => Str::random(40),
            'dns_verified_at' => null,
            'status' => DomainStatus::VERIFICATION_PENDING,
        ]);

        return $domain->fresh();
    }

    /**
     * Check whether the DNS verification token is present in TXT records.
     *
     * In local and test environments, any non-empty token is accepted.
     *
     * @param string $domain
     * @param string $token
     * @return bool
     */
    private function dnsTokenIsPresent(string $domain, string $token): bool
    {
        if (app()->runningUnitTests() || app()->environment('local')) {
            return filled($token);
        }

        $records = @dns_get_record('_tenancy-verify.' . $domain, DNS_TXT) ?: [];
        $records = array_merge($records, @dns_get_record($domain, DNS_TXT) ?: []);

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';

            if (str_contains($txt, $token) || str_contains($txt, 'tenancy-verify=' . $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enable SSL for a DNS-verified domain.
     *
     * Requires DNS verification in non-test environments and simulates
     * immediate SSL provisioning for control plane readiness.
     *
     * @param Domain $domain
     * @return Domain
     *
     * @throws ValidationException
     */
    public function enableSsl(Domain $domain): Domain
    {
        if (!$domain->isVerified() && !app()->runningUnitTests()) {
            throw ValidationException::withMessages([
                'domain' => ['Domain must be DNS-verified before enabling SSL.'],
            ]);
        }

        $domain->update([
            'ssl_enabled' => true,
            'ssl_status' => DomainStatus::SSL_PENDING->value,
            'status' => DomainStatus::SSL_PENDING,
        ]);

        // Provisioning hook: mark active immediately for central control plane readiness.
        $domain->update([
            'ssl_status' => DomainStatus::SSL_ACTIVE->value,
            'ssl_expires_at' => now()->addDays(90),
            'status' => DomainStatus::SSL_ACTIVE,
        ]);

        return $domain->fresh();
    }

    /**
     * Disable SSL for a domain and revert status based on verification state.
     *
     * @param Domain $domain
     * @return Domain
     */
    public function disableSsl(Domain $domain): Domain
    {
        $domain->update([
            'ssl_enabled' => false,
            'ssl_status' => null,
            'ssl_expires_at' => null,
            'status' => $domain->isVerified() ? DomainStatus::ACTIVE : DomainStatus::PENDING,
        ]);

        return $domain->fresh();
    }

    /**
     * Configure domain redirect behavior and update domain type accordingly.
     *
     * @param Domain $domain
     * @param string|null $redirectTo
     * @return Domain
     */
    public function setRedirect(Domain $domain, ?string $redirectTo): Domain
    {
        $domain->update([
            'is_redirect' => filled($redirectTo),
            'redirect_to' => $redirectTo,
            'type' => filled($redirectTo) ? DomainType::REDIRECT : ($domain->is_primary ? DomainType::PRIMARY : DomainType::CUSTOM),
        ]);

        return $domain->fresh();
    }
}
