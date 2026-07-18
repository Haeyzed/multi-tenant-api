<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a subscription plan.
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Plan primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Display name of the plan.
             *
             * @var string
             *
             * @example Pro
             */
            'name' => $this->name,

            /**
             * URL-safe unique identifier.
             *
             * @var string
             *
             * @example pro
             */
            'slug' => $this->slug,

            /**
             * Marketing description of the plan.
             *
             * @var string|null
             */
            'description' => $this->description,

            /**
             * Base price as a decimal string.
             *
             * @var string
             *
             * @example 29.00
             */
            'price' => $this->price,

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example USD
             */
            'currency' => $this->currency,

            /**
             * Billing interval value.
             *
             * @var string|null
             *
             * @example monthly
             */
            'billing_interval' => $this->billing_interval?->value,

            /**
             * Human-readable billing interval label.
             *
             * @var string|null
             */
            'billing_interval_label' => $this->billing_interval?->label(),

            /**
             * Number of trial days included.
             *
             * @var int
             *
             * @example 14
             */
            'trial_days' => $this->trial_days,

            /**
             * Plan status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable plan status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Plan visibility scope value.
             *
             * @var string|null
             *
             * @example public
             */
            'visibility' => $this->visibility?->value,

            /**
             * Human-readable visibility label.
             *
             * @var string|null
             */
            'visibility_label' => $this->visibility?->label(),

            /**
             * Whether the plan is marked as featured.
             *
             * @var bool
             */
            'is_featured' => $this->is_featured,

            /**
             * Whether the plan is visible on public pricing pages.
             *
             * @var bool
             */
            'is_publicly_visible' => $this->isPubliclyVisible(),

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
             * Count of plan features when counted.
             *
             * @var int|null
             *
             * @example 12
             */
            'features_count' => $this->whenCounted('features'),

            /**
             * Related features when eager-loaded.
             *
             * @var list<FeatureResource>|null
             */
            'features' => FeatureResource::collection($this->whenLoaded('features')),

            /**
             * Multi-currency prices when eager-loaded.
             *
             * @var list<PlanPriceResource>|null
             */
            'prices' => PlanPriceResource::collection($this->whenLoaded('prices')),

            /**
             * Best matching price for the requested country/currency (public catalog).
             *
             * @var PlanPriceResource|null
             */
            'resolved_price' => $this->when(
                $this->resolved_price !== null,
                fn () => new PlanPriceResource($this->resolved_price),
            ),

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
             * Human-readable creation time.
             *
             * @var string|null
             *
             * @example 2 hours ago
             */
            'created_at_human' => $this->created_at?->diffForHumans(),

            /**
             * Last update timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'updated_at' => $this->updated_at,

            /**
             * Human-readable last update time.
             *
             * @var string|null
             *
             * @example 5 minutes ago
             */
            'updated_at_human' => $this->updated_at?->diffForHumans(),

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
