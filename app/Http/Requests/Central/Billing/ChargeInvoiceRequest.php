<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for charging an invoice through a payment gateway.
 */
class ChargeInvoiceRequest extends FormRequest
{
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
            'gateway' => ['sometimes', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'force_failure' => ['sometimes', 'boolean'],
            'payment_method' => ['sometimes', 'string'],
            'authorization_code' => ['sometimes', 'string'],
        ];
    }
}
