<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a central tenant record.
 *
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Tenant UUID primary key.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'id' => $this->id,

            /**
             * Display name of the tenant organization.
             *
             * @var string|null
             *
             * @example Acme Corp
             */
            'name' => $this->name,

            /**
             * URL-safe unique identifier for the tenant.
             *
             * @var string|null
             *
             * @example acme-corp
             */
            'slug' => $this->slug,

            /**
             * Primary contact email address.
             *
             * @var string|null
             *
             * @example billing@acme.test
             */
            'email' => $this->email,

            /**
             * Primary contact phone number.
             *
             * @var string|null
             *
             * @example +1-555-0100
             */
            'phone' => $this->phone,

            /**
             * Platform access status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable status label.
             *
             * @var string|null
             *
             * @example Active
             */
            'status_label' => $this->status?->label(),

            /**
             * Whether the tenant may currently access the platform.
             *
             * @var bool
             *
             * @example true
             */
            'can_access' => $this->canAccessPlatform(),

            /**
             * Arbitrary tags assigned to the tenant.
             *
             * @var list<string>
             */
            'tags' => $this->tags ?? [],

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>
             */
            'metadata' => $this->metadata ?? [],

            /**
             * Trial period end timestamp.
             *
             * @var string|null
             *
             * @format date-time
             *
             * @example 2026-08-01T00:00:00.000000Z
             */
            'trial_ends_at' => $this->trial_ends_at,

            /**
             * Suspension timestamp, if suspended.
             *
             * @var string|null
             *
             * @format date-time
             */
            'suspended_at' => $this->suspended_at,

            /**
             * Reason provided when the tenant was suspended.
             *
             * @var string|null
             */
            'suspended_reason' => $this->suspended_reason,

            /**
             * Archive timestamp, if archived.
             *
             * @var string|null
             *
             * @format date-time
             */
            'archived_at' => $this->archived_at,

            /**
             * Count of associated domains when counted.
             *
             * @var int|null
             *
             * @example 2
             */
            'domains_count' => $this->whenCounted('domains'),

            /**
             * Count of associated notes when counted.
             *
             * @var int|null
             *
             * @example 5
             */
            'notes_count' => $this->whenCounted('notes'),

            /**
             * Related domains when eager-loaded.
             *
             * @var list<DomainResource>|null
             */
            'domains' => DomainResource::collection($this->whenLoaded('domains')),

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
             * Human-readable creation time.
             *
             * @var string|null
             *
             * @example 2 hours ago
             */
            'created_at_human' => $this->created_at?->diffForHumans(),

            /**
             * Last update timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'updated_at' => $this->updated_at,

            /**
             * Human-readable last update time.
             *
             * @var string|null
             *
             * @example 5 minutes ago
             */
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            /**
             * Soft-delete timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
