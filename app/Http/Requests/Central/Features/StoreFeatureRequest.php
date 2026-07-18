<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Features;

use App\Enums\Central\FeatureStatus;
use App\Enums\Central\PlanFeatureLimitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a billable platform feature.
 */
class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('features.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional feature category identifier.
             * @var int|null
             * @example 3
             */
            'feature_category_id' => ['sometimes', 'nullable', 'exists:feature_categories,id'],

            /**
             * Human-readable feature name.
             * @var string
             * @example Advanced Analytics
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly feature slug.
             * @var string
             * @example advanced-analytics
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:features,slug'],

            /**
             * Internal feature key used in code and entitlements.
             * @var string
             * @example analytics.advanced
             */
            'key' => ['sometimes', 'string', 'max:255', 'unique:features,key'],

            /**
             * Optional marketing or admin description.
             * @var string|null
             * @example Unlock cohort and funnel reporting.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Icon identifier for UI surfaces.
             * @var string|null
             * @example chart-bar
             */
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],

            /**
             * Feature lifecycle status.
             * @var string
             * @example active
             */
            'status' => ['sometimes', Rule::enum(FeatureStatus::class)],

            /**
             * Default limit measurement type for plan assignments.
             * @var string
             * @example count
             */
            'default_limit_type' => ['sometimes', Rule::enum(PlanFeatureLimitType::class)],

            /**
             * Default numeric limit when a plan does not override it.
             * @var int|null
             * @example 1000
             */
            'default_limit_value' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Unit label shown alongside limit values.
             * @var string|null
             * @example reports
             */
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],

            /**
             * Whether the feature can be assigned to plans.
             * @var bool
             * @example true
             */
            'is_available' => ['sometimes', 'boolean'],

            /**
             * Whether usage against this feature should be metered.
             * @var bool
             * @example true
             */
            'tracks_usage' => ['sometimes', 'boolean'],

            /**
             * Display order within feature listings.
             * @var int
             * @example 20
             */
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            /**
             * Arbitrary feature metadata key-value pairs.
             * @var array<string, mixed>
             * @example {"docs_url":"https://docs.example.com/analytics"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
