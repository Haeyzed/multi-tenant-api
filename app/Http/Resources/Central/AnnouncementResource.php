<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform announcement.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Announcement primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Announcement title.
             *
             * @var string
             *
             * @example Scheduled Maintenance
             */
            'title' => $this->title,

            /**
             * Announcement body content.
             *
             * @var string
             */
            'body' => $this->body,

            /**
             * Announcement type value.
             *
             * @var string|null
             *
             * @example info
             */
            'type' => $this->type?->value,

            /**
             * Human-readable announcement type label.
             *
             * @var string|null
             */
            'type_label' => $this->type?->label(),

            /**
             * Audience target scope value.
             *
             * @var string|null
             *
             * @example all
             */
            'target' => $this->target?->value,

            /**
             * Publication status value.
             *
             * @var string|null
             *
             * @example published
             */
            'status' => $this->status?->value,

            /**
             * Whether users may dismiss the announcement.
             *
             * @var bool
             */
            'is_dismissible' => $this->is_dismissible,

            /**
             * Plan IDs targeted by this announcement.
             *
             * @var list<int>|null
             */
            'target_plan_ids' => $this->target_plan_ids,

            /**
             * Tenant UUIDs targeted by this announcement.
             *
             * @var list<string>|null
             */
            'target_tenant_ids' => $this->target_tenant_ids,

            /**
             * Geographic regions targeted by this announcement.
             *
             * @var list<string>|null
             */
            'regions' => $this->regions,

            /**
             * Visibility start timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'starts_at' => $this->starts_at,

            /**
             * Visibility end timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'ends_at' => $this->ends_at,

            /**
             * Publication timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'published_at' => $this->published_at,

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>|null
             */
            'metadata' => $this->metadata,

            /**
             * Creator summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
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
