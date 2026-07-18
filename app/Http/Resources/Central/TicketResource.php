<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a support ticket.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Ticket primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Human-readable ticket number.
             *
             * @var string
             *
             * @example TKT-2026-0001
             */
            'number' => $this->number,

            /**
             * Owning tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,

            /**
             * Assigned ticket category ID.
             *
             * @var int|null
             */
            'ticket_category_id' => $this->ticket_category_id,

            /**
             * Ticket subject line.
             *
             * @var string
             *
             * @example Cannot access dashboard
             */
            'subject' => $this->subject,

            /**
             * Initial ticket description.
             *
             * @var string
             */
            'description' => $this->description,

            /**
             * Ticket status value.
             *
             * @var string|null
             *
             * @example open
             */
            'status' => $this->status?->value,

            /**
             * Ticket priority value.
             *
             * @var string|null
             *
             * @example high
             */
            'priority' => $this->priority?->value,

            /**
             * SLA response window in hours for the current priority.
             *
             * @var int|null
             *
             * @example 4
             */
            'priority_sla_hours' => $this->priority?->slaHours(),

            /**
             * Resolution timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'resolved_at' => $this->resolved_at,

            /**
             * Closure timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'closed_at' => $this->closed_at,

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>|null
             */
            'metadata' => $this->metadata,

            /**
             * Related category when eager-loaded.
             *
             * @var TicketCategoryResource|null
             */
            'category' => TicketCategoryResource::make($this->whenLoaded('category')),

            /**
             * Related tenant summary when eager-loaded.
             *
             * @var array{id: string, name: string|null, slug: string|null}|null
             */
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
                'slug' => $this->tenant?->slug,
            ]),

            /**
             * Ticket creator summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),

            /**
             * Assigned agent summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ]),

            /**
             * Ticket replies when eager-loaded.
             *
             * @var list<TicketReplyResource>|null
             */
            'replies' => TicketReplyResource::collection($this->whenLoaded('replies')),

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
