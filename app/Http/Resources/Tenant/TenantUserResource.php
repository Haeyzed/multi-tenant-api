<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a user within a tenant context.
 *
 * @mixin User
 */
class TenantUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Tenant user primary key.
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
             * @example John Doe
             */
            'name' => $this->name,

            /**
             * Login email address.
             *
             * @var string
             *
             * @example user@tenant.test
             */
            'email' => $this->email,

            /**
             * Whether this user is the tenant owner.
             *
             * @var bool
             */
            'is_owner' => $this->is_owner,

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
