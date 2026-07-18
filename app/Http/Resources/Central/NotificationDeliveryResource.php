<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform notification delivery record.
 *
 * @mixin NotificationDelivery
 */
class NotificationDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Delivery record primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Parent platform notification ID.
             *
             * @var int
             */
            'platform_notification_id' => $this->platform_notification_id,

            /**
             * Target user ID.
             *
             * @var int
             */
            'user_id' => $this->user_id,

            /**
             * Delivery channel value.
             *
             * @var string|null
             *
             * @example email
             */
            'channel' => $this->channel?->value,

            /**
             * Delivery status value.
             *
             * @var string|null
             *
             * @example delivered
             */
            'status' => $this->status?->value,

            /**
             * Delivery completion timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'delivered_at' => $this->delivered_at,

            /**
             * Read timestamp by the recipient.
             *
             * @var string|null
             *
             * @format date-time
             */
            'read_at' => $this->read_at,

            /**
             * Error message when delivery failed.
             *
             * @var string|null
             */
            'error' => $this->error,

            /**
             * Parent notification when eager-loaded.
             *
             * @var PlatformNotificationResource|null
             */
            'notification' => PlatformNotificationResource::make($this->whenLoaded('notification')),

            /**
             * Recipient user summary when eager-loaded.
             *
             * @var array{id: int, name: string, email: string}|null
             */
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
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
        ];
    }
}
