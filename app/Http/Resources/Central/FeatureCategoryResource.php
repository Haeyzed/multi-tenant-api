<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\FeatureCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a feature category.
 *
 * @mixin FeatureCategory
 */
class FeatureCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Feature category primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Display name of the category.
             *
             * @var string
             *
             * @example Integrations
             */
            'name' => $this->name,

            /**
             * URL-safe unique identifier.
             *
             * @var string
             *
             * @example integrations
             */
            'slug' => $this->slug,

            /**
             * Category description.
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
             * Display sort order.
             *
             * @var int
             */
            'sort_order' => $this->sort_order,

            /**
             * Whether the category is active.
             *
             * @var bool
             */
            'is_active' => $this->is_active,

            /**
             * Count of features in this category when counted.
             *
             * @var int|null
             *
             * @example 8
             */
            'features_count' => $this->whenCounted('features'),

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
