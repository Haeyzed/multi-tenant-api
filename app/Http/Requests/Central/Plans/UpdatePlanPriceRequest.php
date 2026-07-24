<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating an existing plan price.
 */
class UpdatePlanPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $this->user()?->can('update', $plan) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Price amount in major currency units.
             *
             * @var float
             *
             * @example 59.99
             */
            'amount' => ['sometimes', 'numeric', 'min:0'],

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example USD
             */
            'currency' => ['sometimes', 'string', 'size:3'],

            /**
             * Billing cadence for this price.
             *
             * @var string
             *
             * @example yearly
             */
            'billing_interval' => ['sometimes', 'string', 'in:monthly,quarterly,yearly'],

            /**
             * Trial length in days for this price.
             *
             * @var int|null
             *
             * @example 7
             */
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],

            /**
             * Price lifecycle status.
             *
             * @var string
             *
             * @example inactive
             */
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,archived'],

            /**
             * Arbitrary price metadata key-value pairs.
             *
             * @var array<string, mixed>|null
             *
             * @example {"display_label":"Pro Yearly"}
             */
            'metadata' => ['sometimes', 'nullable', 'array'],

            /**
             * External payment gateway price identifiers.
             *
             * @var array<string, string|null>|null
             *
             * @example {"stripe":"price_1XYZ","paystack":"PLN_xyz","flutterwave":"flw_price_2"}
             */
            'gateway_identifiers' => ['sometimes', 'nullable', 'array'],

            /**
             * Stripe price identifier.
             *
             * @var string|null
             *
             * @example price_1XYZ
             */
            'gateway_identifiers.stripe' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Paystack plan or price identifier.
             *
             * @var string|null
             *
             * @example PLN_xyz
             */
            'gateway_identifiers.paystack' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Flutterwave plan or price identifier.
             *
             * @var string|null
             *
             * @example flw_price_2
             */
            'gateway_identifiers.flutterwave' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
