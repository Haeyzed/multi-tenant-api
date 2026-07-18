<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\TenantImpersonation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant impersonation session.
 *
 * @mixin TenantImpersonation
 */
class TenantImpersonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Impersonation session primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Target tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,

            /**
             * Platform user ID performing the impersonation.
             *
             * @var int
             */
            'user_id' => $this->user_id,

            /**
             * Impersonation reason value.
             *
             * @var string|null
             *
             * @example support
             */
            'reason' => $this->reason?->value,

            /**
             * Human-readable impersonation reason label.
             *
             * @var string|null
             */
            'reason_label' => $this->reason?->label(),

            /**
             * Free-text notes explaining the reason.
             *
             * @var string|null
             */
            'reason_notes' => $this->reason_notes,

            /**
             * Session status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable session status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Client IP address when the session started.
             *
             * @var string|null
             *
             * @example 192.168.1.1
             */
            'ip_address' => $this->ip_address,

            /**
             * Session expiration timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'expires_at' => $this->expires_at,

            /**
             * Session end timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'ended_at' => $this->ended_at,

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
             * Related platform user summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
        ];
    }
}
