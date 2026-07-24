<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for refunding a payment.
 */
class RefundPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('billing.payments.refund') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional partial refund amount.
             *
             * @var float
             *
             * @example 25.00
             */
            'amount' => ['sometimes', 'numeric', 'min:0.01'],

            /**
             * Optional refund reason.
             *
             * @var string|null
             *
             * @example Duplicate charge
             */
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
