<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\SubscriptionHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a subscription lifecycle history entry.
 *
 * @mixin SubscriptionHistory
 */
class SubscriptionHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * History entry primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Event name describing the transition.
             *
             * @var string
             *
             * @example plan_changed
             */
            'event' => $this->event,

            /**
             * Previous subscription status value.
             *
             * @var string|null
             */
            'from_status' => $this->from_status?->value,

            /**
             * New subscription status value.
             *
             * @var string|null
             *
             * @example active
             */
            'to_status' => $this->to_status?->value,

            /**
             * Previous plan ID before the change.
             *
             * @var int|null
             */
            'from_plan_id' => $this->from_plan_id,

            /**
             * New plan ID after the change.
             *
             * @var int|null
             */
            'to_plan_id' => $this->to_plan_id,

            /**
             * ID of the user who performed the action.
             *
             * @var int|null
             */
            'user_id' => $this->user_id,

            /**
             * Additional structured metadata for the event.
             *
             * @var array<string, mixed>|null
             */
            'meta' => $this->meta,

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
