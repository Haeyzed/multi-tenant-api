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
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where('status', PlanStatus::Active->value),
            ],
            'plan_price_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('plan_prices', 'id')->where('status', PlanStatus::Active->value),
            ],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'billing_interval' => ['sometimes', 'nullable', 'string', 'in:monthly,quarterly,yearly'],
        ];
    }
}
