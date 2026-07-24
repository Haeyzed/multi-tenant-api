<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for upgrading or downgrading a subscription plan.
 */
class ChangeSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $this->user()?->can('manage', $subscription) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Target active plan identifier.
             *
             * @var int
             *
             * @example 3
             */
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where('status', PlanStatus::Active->value),
            ],

            /**
             * Optional active plan price identifier.
             *
             * @var int|null
             *
             * @example 7
             */
            'plan_price_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('plan_prices', 'id')->where('status', PlanStatus::Active->value),
            ],

            /**
             * ISO 3166-1 alpha-2 country code used for pricing.
             *
             * @var string|null
             *
             * @example NG
             */
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],

            /**
             * ISO 4217 currency code.
             *
             * @var string|null
             *
             * @example NGN
             */
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            /**
             * Billing interval for the new plan price.
             *
             * @var string|null
             *
             * @example monthly
             */
            'billing_interval' => ['sometimes', 'nullable', 'string', 'in:monthly,quarterly,yearly'],
        ];
    }
}
