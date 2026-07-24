<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a subscription.
 */
class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('subscriptions.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
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
            'gateway' => ['sometimes', 'string'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'billing_address_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('billing_addresses', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id'))),
            ],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
