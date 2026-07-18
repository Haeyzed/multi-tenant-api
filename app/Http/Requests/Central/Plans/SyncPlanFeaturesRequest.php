<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a full feature sync payload for a subscription plan.
 */
class SyncPlanFeaturesRequest extends FormRequest
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
             * Complete list of feature assignments for the plan.
             * @var list<array<string, mixed>>
             * @example [{"feature_id":12,"limit_type":"count","limit_value":1000,"is_enabled":true}]
             */
            'features' => ['required', 'array'],

            /**
             * Feature identifier for a plan assignment row.
             * @var int
             * @example 12
             */
            'features.*.feature_id' => ['required', 'integer', 'exists:features,id'],

            /**
             * Limit measurement type for the assigned feature.
             * @var string
             * @example count
             */
            'features.*.limit_type' => ['sometimes', Rule::enum(PlanFeatureLimitType::class)],

            /**
             * Numeric limit value for the assigned feature.
             * @var int|null
             * @example 1000
             */
            'features.*.limit_value' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Whether the feature has no enforced limit.
             * @var bool
             * @example false
             */
            'features.*.is_unlimited' => ['sometimes', 'boolean'],

            /**
             * Whether the feature is enabled on the plan.
             * @var bool
             * @example true
             */
            'features.*.is_enabled' => ['sometimes', 'boolean'],

            /**
             * Whether usage should be metered for this assignment.
             * @var bool
             * @example true
             */
            'features.*.tracks_usage' => ['sometimes', 'boolean'],

            /**
             * Usage reset interval for periodic limits.
             * @var string|null
             * @example monthly
             */
            'features.*.reset_period' => ['sometimes', 'nullable', Rule::in(SubscriptionInterval::recurringValues())],

            /**
             * Arbitrary assignment metadata key-value pairs.
             * @var array<string, mixed>
             * @example {"overage_allowed":true}
             */
            'features.*.metadata' => ['sometimes', 'array'],
        ];
    }
}
