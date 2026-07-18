<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API representation of a personal API access token.
 *
 * @mixin PersonalAccessToken
 */
class PersonalAccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Token primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * User-defined token label.
             *
             * @var string
             *
             * @example Mobile App
             */
            'name' => $this->name,

            /**
             * Token ability scopes.
             *
             * @var list<string>
             *
             * @example ["*"]
             */
            'abilities' => $this->abilities,

            /**
             * Last usage timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'last_used_at' => $this->last_used_at,

            /**
             * Token expiration timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'expires_at' => $this->expires_at,

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
             * Whether this token is the one used for the current request.
             *
             * @var bool
             */
            'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
        ];
    }
}
