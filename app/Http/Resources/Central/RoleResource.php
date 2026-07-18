<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform role.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Role primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Unique role name.
             *
             * @var string
             *
             * @example admin
             */
            'name' => $this->name,

            /**
             * Authentication guard the role belongs to.
             *
             * @var string
             *
             * @example web
             */
            'guard_name' => $this->guard_name,

            /**
             * Assigned permission names when permissions are eager-loaded.
             *
             * @var list<string>|null
             *
             * @example ["tenants.view", "tenants.update"]
             */
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),

            /**
             * Count of users with this role when counted.
             *
             * @var int|null
             *
             * @example 3
             */
            'users_count' => $this->whenCounted('users'),

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
