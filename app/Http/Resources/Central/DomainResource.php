<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant domain record.
 *
 * @mixin Domain
 */
class DomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Domain record primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Owning tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,

            /**
             * Fully qualified domain name.
             *
             * @var string
             *
             * @example acme.example.test
             */
            'domain' => $this->domain,

            /**
             * Domain type value.
             *
             * @var string|null
             *
             * @example subdomain
             */
            'type' => $this->type?->value,

            /**
             * Human-readable domain type label.
             *
             * @var string|null
             */
            'type_label' => $this->type?->label(),

            /**
             * Domain verification status value.
             *
             * @var string|null
             *
             * @example verified
             */
            'status' => $this->status?->value,

            /**
             * Human-readable domain status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Whether this is the tenant's primary domain.
             *
             * @var bool
             */
            'is_primary' => $this->is_primary,

            /**
             * Whether HTTP requests redirect elsewhere.
             *
             * @var bool
             */
            'is_redirect' => $this->is_redirect,

            /**
             * Target URL when redirect is enabled.
             *
             * @var string|null
             */
            'redirect_to' => $this->redirect_to,

            /**
             * DNS verification token (visible only to authorized users).
             *
             * @var string|null
             */
            'dns_verification_token' => $this->when(
                $request->user()?->can('domains.verify'),
                $this->dns_verification_token
            ),

            /**
             * DNS verification completion timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'dns_verified_at' => $this->dns_verified_at,

            /**
             * Whether SSL/TLS is enabled for this domain.
             *
             * @var bool
             */
            'ssl_enabled' => $this->ssl_enabled,

            /**
             * Current SSL certificate status.
             *
             * @var string|null
             */
            'ssl_status' => $this->ssl_status,

            /**
             * SSL certificate expiration timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'ssl_expires_at' => $this->ssl_expires_at,

            /**
             * Whether HTTPS is enforced for this domain.
             *
             * @var bool
             */
            'force_https' => $this->force_https,

            /**
             * Creation timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             *
             * @example 2026-07-13T11:22:26.000000Z
             */
            'created_at' => $this->created_at,

            /**
             * Last update timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
