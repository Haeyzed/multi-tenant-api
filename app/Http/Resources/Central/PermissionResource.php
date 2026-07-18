<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * API representation of a platform permission.
 *
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Permission primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Unique permission identifier.
             *
             * @var string
             *
             * @example tenants.view
             */
            'name' => $this->name,

            /**
             * Authentication guard the permission belongs to.
             *
             * @var string
             *
             * @example web
             */
            'guard_name' => $this->guard_name,

            /**
             * Permission group derived from the name prefix.
             *
             * @var string
             *
             * @example tenants
             */
            'group' => explode('.', $this->name)[0] ?? 'other',

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
