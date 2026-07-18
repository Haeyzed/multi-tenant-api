<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Feature;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform feature definition.
 *
 * @mixin Feature
 */
class FeatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Feature primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Parent feature category ID.
             *
             * @var int|null
             */
            'feature_category_id' => $this->feature_category_id,

            /**
             * Display name of the feature.
             *
             * @var string
             *
             * @example API Access
             */
            'name' => $this->name,

            /**
             * URL-safe unique identifier.
             *
             * @var string
             *
             * @example api-access
             */
            'slug' => $this->slug,

            /**
             * Machine-readable feature key.
             *
             * @var string
             *
             * @example api.access
             */
            'key' => $this->key,

            /**
             * Feature description.
             *
             * @var string|null
             */
            'description' => $this->description,

            /**
             * Icon identifier for UI display.
             *
             * @var string|null
             */
            'icon' => $this->icon,

            /**
             * Feature status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable feature status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Default limit type value.
             *
             * @var string|null
             *
             * @example count
             */
            'default_limit_type' => $this->default_limit_type?->value,

            /**
             * Default limit value when a limit applies.
             *
             * @var int|null
             */
            'default_limit_value' => $this->default_limit_value,

            /**
             * Unit label for limit values.
             *
             * @var string|null
             *
             * @example requests
             */
            'unit' => $this->unit,

            /**
             * Whether the feature is generally available.
             *
             * @var bool
             */
            'is_available' => $this->is_available,

            /**
             * Whether usage of this feature is tracked.
             *
             * @var bool
             */
            'tracks_usage' => $this->tracks_usage,

            /**
             * Display sort order.
             *
             * @var int
             */
            'sort_order' => $this->sort_order,

            /**
             * Custom metadata key-value pairs.
             *
             * @var array<string, mixed>
             */
            'metadata' => $this->metadata ?? [],

            /**
             * Parent category when eager-loaded.
             *
             * @var FeatureCategoryResource|null
             */
            'category' => new FeatureCategoryResource($this->whenLoaded('category')),

            /**
             * Plan pivot attributes when loaded via plan relationship.
             *
             * @var array{id: int, limit_type: string|null, limit_value: int|null, is_unlimited: bool, is_enabled: bool, tracks_usage: bool, reset_period: string|null}|null
             */
            'pivot' => $this->whenPivotLoaded('plan_feature', fn () => [
                'id' => $this->pivot->id,
                'limit_type' => $this->pivot->limit_type instanceof BackedEnum
                    ? $this->pivot->limit_type->value
                    : $this->pivot->limit_type,
                'limit_value' => $this->pivot->limit_value,
                'is_unlimited' => (bool) $this->pivot->is_unlimited,
                'is_enabled' => (bool) $this->pivot->is_enabled,
                'tracks_usage' => (bool) $this->pivot->tracks_usage,
                'reset_period' => $this->pivot->reset_period instanceof BackedEnum
                    ? $this->pivot->reset_period->value
                    : $this->pivot->reset_period,
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

            /**
             * Soft-delete timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
