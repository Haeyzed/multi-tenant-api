<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for charging an invoice through a payment gateway.
 */
class ChargeInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('billing.payments.charge') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Payment gateway driver to charge through.
             *
             * @var string
             *
             * @example stripe
             */
            'gateway' => ['sometimes', 'string'],

            /**
             * Optional amount override for the charge.
             *
             * @var float
             *
             * @example 49.99
             */
            'amount' => ['sometimes', 'numeric', 'min:0.01'],

            /**
             * Force a simulated payment failure (testing).
             *
             * @var bool
             *
             * @example false
             */
            'force_failure' => ['sometimes', 'boolean'],

            /**
             * Payment method token or identifier.
             *
             * @var string
             *
             * @example pm_card_visa
             */
            'payment_method' => ['sometimes', 'string'],

            /**
             * Saved authorization code for recurring charges.
             *
             * @var string
             *
             * @example AUTH_ab12cd34ef
             */
            'authorization_code' => ['sometimes', 'string'],
        ];
    }
}
