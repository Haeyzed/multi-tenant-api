<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a subscription plan.
 */
class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plans.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Plan display name.
             *
             * @var string
             *
             * @example Pro
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly plan slug.
             *
             * @var string
             *
             * @example pro
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:plans,slug'],

            /**
             * Optional marketing description.
             *
             * @var string|null
             *
             * @example Best for growing teams needing advanced features.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Billing cadence for the plan (mirrored from primary plan price).
             *
             * @var string
             *
             * @example monthly
             */
            'billing_interval' => ['sometimes', Rule::enum(SubscriptionInterval::class)],

            /**
             * Number of trial days included with signup.
             *
             * @var int
             *
             * @example 14
             */
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],

            /**
             * Plan lifecycle status.
             *
             * @var string
             *
             * @example active
             */
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],

            /**
             * Who can discover or purchase the plan.
             *
             * @var string
             *
             * @example public
             */
            'visibility' => ['sometimes', Rule::enum(PlanVisibility::class)],

            /**
             * Whether the plan is highlighted in pricing UI.
             *
             * @var bool
             *
             * @example true
             */
            'is_featured' => ['sometimes', 'boolean'],

            /**
             * Display order within plan listings.
             *
             * @var int
             *
             * @example 2
             */
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            /**
             * Arbitrary plan metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"badge":"Most Popular"}
             */
            'metadata' => ['sometimes', 'array'],

            /**
             * Feature assignments to create with the plan.
             *
             * @var list<array<string, mixed>>
             *
             * @example [{"feature_id":12,"limit_type":"count","limit_value":1000}]
             */
            'features' => ['sometimes', 'array'],

            /**
             * Feature identifier for a plan assignment row.
             *
             * @var int
             *
             * @example 12
             */
            'features.*.feature_id' => ['required', 'integer', 'exists:features,id'],

            /**
             * Limit measurement type for the assigned feature.
             *
             * @var string
             *
             * @example count
             */
            'features.*.limit_type' => ['sometimes', Rule::enum(PlanFeatureLimitType::class)],

            /**
             * Numeric limit value for the assigned feature.
             *
             * @var int|null
             *
             * @example 1000
             */
            'features.*.limit_value' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Whether the feature has no enforced limit.
             *
             * @var bool
             *
             * @example false
             */
            'features.*.is_unlimited' => ['sometimes', 'boolean'],

            /**
             * Whether the feature is enabled on the plan.
             *
             * @var bool
             *
             * @example true
             */
            'features.*.is_enabled' => ['sometimes', 'boolean'],

            /**
             * Whether usage should be metered for this assignment.
             *
             * @var bool
             *
             * @example true
             */
            'features.*.tracks_usage' => ['sometimes', 'boolean'],

            /**
             * Usage reset interval for periodic limits.
             *
             * @var string|null
             *
             * @example monthly
             */
            'features.*.reset_period' => ['sometimes', 'nullable', Rule::in(SubscriptionInterval::recurringValues())],
        ];
    }
}
