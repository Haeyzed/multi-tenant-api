<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Models\Central\BillingProfile;
use App\Models\Central\Tenant;
use Illuminate\Support\Str;

/**
 * Manages per-tenant billing preferences used for price and gateway resolution.
 */
final class BillingProfileService
{
    /**
     * Get or create an empty billing profile for the tenant.
     */
    public function forTenant(Tenant $tenant): BillingProfile
    {
        return BillingProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'country_iso2' => null,
                'currency' => null,
                'preferred_gateway' => null,
                'metadata' => null,
            ],
        );
    }

    /**
     * @param  array{country_iso2?: string|null, currency?: string|null, preferred_gateway?: string|null, metadata?: array<string, mixed>|null}  $attributes
     */
    public function update(Tenant $tenant, array $attributes): BillingProfile
    {
        $payload = [];

        if (array_key_exists('country_iso2', $attributes)) {
            $payload['country_iso2'] = filled($attributes['country_iso2'] ?? null)
                ? Str::upper((string) $attributes['country_iso2'])
                : null;
        }

        if (array_key_exists('currency', $attributes)) {
            $payload['currency'] = filled($attributes['currency'] ?? null)
                ? Str::upper((string) $attributes['currency'])
                : null;
        }

        if (array_key_exists('preferred_gateway', $attributes)) {
            $payload['preferred_gateway'] = filled($attributes['preferred_gateway'] ?? null)
                ? Str::lower((string) $attributes['preferred_gateway'])
                : null;
        }

        if (array_key_exists('metadata', $attributes)) {
            $payload['metadata'] = $attributes['metadata'];
        }

        return BillingProfile::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $payload,
        );
    }
}
