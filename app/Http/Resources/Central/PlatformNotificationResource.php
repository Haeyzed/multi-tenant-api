<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform notification broadcast.
 *
 * @mixin PlatformNotification
 */
class PlatformNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Platform notification primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Notification title.
             *
             * @var string
             *
             * @example System Update
             */
            'title' => $this->title,

            /**
             * Notification body content.
             *
             * @var string
             */
            'body' => $this->body,

            /**
             * Delivery channels enabled for this notification.
             *
             * @var list<string>
             *
             * @example ["database", "mail"]
             */
            'channels' => $this->channels,

            /**
             * Notification status value.
             *
             * @var string|null
             *
             * @example sent
             */
            'status' => $this->status?->value,

            /**
             * Scheduled send timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'scheduled_at' => $this->scheduled_at,

            /**
             * Actual send timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'sent_at' => $this->sent_at,

            /**
             * Target user IDs for this notification.
             *
             * @var list<int>|null
             */
            'target_user_ids' => $this->target_user_ids,

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>|null
             */
            'metadata' => $this->metadata,

            /**
             * Count of delivery records when counted.
             *
             * @var int|null
             *
             * @example 150
             */
            'deliveries_count' => $this->whenCounted('deliveries'),

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
