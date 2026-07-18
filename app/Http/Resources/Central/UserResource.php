<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a central platform user.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * User primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Full display name.
             *
             * @var string
             *
             * @example Jane Admin
             */
            'name' => $this->name,

            /**
             * Login email address.
             *
             * @var string
             *
             * @example admin@example.test
             */
            'email' => $this->email,

            /**
             * Contact phone number.
             *
             * @var string|null
             */
            'phone' => $this->phone,

            /**
             * Preferred IANA timezone identifier.
             *
             * @var string|null
             *
             * @example America/New_York
             */
            'timezone' => $this->timezone,

            /**
             * Account status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable account status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Email verification timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'email_verified_at' => $this->email_verified_at,

            /**
             * Most recent login timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'last_login_at' => $this->last_login_at,

            /**
             * IP address of the most recent login.
             *
             * @var string|null
             *
             * @example 192.168.1.1
             */
            'last_login_ip' => $this->last_login_ip,

            /**
             * Whether two-factor authentication is enabled.
             *
             * @var bool
             */
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),

            /**
             * Absolute URL to the user's avatar image.
             *
             * @var string|null
             */
            'avatar_url' => $this->avatarUrl(),

            /**
             * Assigned role names when roles are eager-loaded.
             *
             * @var list<string>|null
             *
             * @example ["admin", "support"]
             */
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),

            /**
             * Effective permission names when roles or permissions are eager-loaded.
             *
             * @var list<string>|null
             *
             * @example ["tenants.view", "tenants.update"]
             */
            'permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->values()
            ),

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
