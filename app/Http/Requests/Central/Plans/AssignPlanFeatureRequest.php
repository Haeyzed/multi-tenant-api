<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for assigning a single feature to a plan.
 */
class AssignPlanFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return $this->user()?->can('manageFeatures', $plan) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Feature identifier to attach to the plan.
             * @var int
             * @example 12
             */
            'feature_id' => ['required', 'integer', 'exists:features,id'],

            /**
             * Limit measurement type for the assigned feature.
             * @var string
             * @example count
             */
            'limit_type' => ['sometimes', Rule::enum(PlanFeatureLimitType::class)],

            /**
             * Numeric limit value for the assigned feature.
             * @var int|null
             * @example 1000
             */
            'limit_value' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Whether the feature has no enforced limit.
             * @var bool
             * @example false
             */
            'is_unlimited' => ['sometimes', 'boolean'],

            /**
             * Whether the feature is enabled on the plan.
             * @var bool
             * @example true
             */
            'is_enabled' => ['sometimes', 'boolean'],

            /**
             * Whether usage should be metered for this assignment.
             * @var bool
             * @example true
             */
            'tracks_usage' => ['sometimes', 'boolean'],

            /**
             * Usage reset interval for periodic limits.
             * @var string|null
             * @example monthly
             */
            'reset_period' => ['sometimes', 'nullable', Rule::in(SubscriptionInterval::recurringValues())],

            /**
             * Arbitrary assignment metadata key-value pairs.
             * @var array<string, mixed>
             * @example {"overage_allowed":true}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
