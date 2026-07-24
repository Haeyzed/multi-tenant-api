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
    /**
     * Determine if the user is authorized to make this request.
     */
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
            /**
             * Tenant UUID to subscribe.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],

            /**
             * Active plan identifier.
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
             * Billing interval for the subscription.
             *
             * @var string|null
             *
             * @example monthly
             */
            'billing_interval' => ['sometimes', 'nullable', 'string', 'in:monthly,quarterly,yearly'],

            /**
             * Payment gateway driver for the subscription.
             *
             * @var string
             *
             * @example stripe
             */
            'gateway' => ['sometimes', 'string'],

            /**
             * Number of trial days before billing starts.
             *
             * @var int|null
             *
             * @example 14
             */
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Optional billing address for the subscription.
             *
             * @var int|null
             *
             * @example 8
             */
            'billing_address_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('billing_addresses', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id'))),
            ],

            /**
             * Tax rate percentage applied to invoices.
             *
             * @var float
             *
             * @example 7.5
             */
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
