<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * API representation of an activity log entry.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Activity log entry primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Logical log channel name.
             *
             * @var string|null
             *
             * @example default
             */
            'log_name' => $this->log_name,

            /**
             * Human-readable activity description.
             *
             * @var string
             */
            'description' => $this->description,

            /**
             * Event name that triggered the log entry.
             *
             * @var string|null
             *
             * @example updated
             */
            'event' => $this->event,

            /**
             * Fully qualified class name of the affected model.
             *
             * @var string|null
             */
            'subject_type' => $this->subject_type,

            /**
             * Primary key of the affected model.
             *
             * @var int|string|null
             */
            'subject_id' => $this->subject_id,

            /**
             * Fully qualified class name of the actor.
             *
             * @var string|null
             */
            'causer_type' => $this->causer_type,

            /**
             * Primary key of the actor.
             *
             * @var int|string|null
             */
            'causer_id' => $this->causer_id,

            /**
             * Additional structured properties for the activity.
             *
             * @var array<string, mixed>|null
             */
            'properties' => $this->properties,

            /**
             * Batch UUID grouping related activities.
             *
             * @var string|null
             */
            'batch_uuid' => $this->batch_uuid,

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
