<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a plan-feature assignment.
 *
 * @mixin PlanFeature
 */
class PlanFeatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Plan-feature assignment primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Associated plan ID.
             *
             * @var int
             */
            'plan_id' => $this->plan_id,

            /**
             * Associated feature ID.
             *
             * @var int
             */
            'feature_id' => $this->feature_id,

            /**
             * Limit type value for this plan.
             *
             * @var string|null
             *
             * @example count
             */
            'limit_type' => $this->limit_type?->value,

            /**
             * Human-readable limit type label.
             *
             * @var string|null
             */
            'limit_type_label' => $this->limit_type?->label(),

            /**
             * Numeric limit value when applicable.
             *
             * @var int|null
             */
            'limit_value' => $this->limit_value,

            /**
             * Whether usage is unlimited for this plan.
             *
             * @var bool
             */
            'is_unlimited' => $this->is_unlimited,

            /**
             * Whether the feature is enabled on this plan.
             *
             * @var bool
             */
            'is_enabled' => $this->is_enabled,

            /**
             * Whether usage is tracked for this assignment.
             *
             * @var bool
             */
            'tracks_usage' => $this->tracks_usage,

            /**
             * Usage reset period value.
             *
             * @var string|null
             *
             * @example monthly
             */
            'reset_period' => $this->reset_period?->value,

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>
             */
            'metadata' => $this->metadata ?? [],

            /**
             * Related feature when eager-loaded.
             *
             * @var FeatureResource|null
             */
            'feature' => new FeatureResource($this->whenLoaded('feature')),

            /**
             * Related plan when eager-loaded.
             *
             * @var PlanResource|null
             */
            'plan' => new PlanResource($this->whenLoaded('plan')),

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
