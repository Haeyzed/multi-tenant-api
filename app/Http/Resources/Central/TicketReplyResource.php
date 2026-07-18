<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a support ticket reply.
 *
 * @mixin TicketReply
 */
class TicketReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Ticket reply primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Parent ticket ID.
             *
             * @var int
             */
            'ticket_id' => $this->ticket_id,

            /**
             * Reply body content.
             *
             * @var string
             */
            'body' => $this->body,

            /**
             * Whether the reply is internal-only.
             *
             * @var bool
             */
            'is_internal' => $this->is_internal,

            /**
             * Author summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'email' => $this->author?->email,
            ]),

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
