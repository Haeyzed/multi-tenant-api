<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates optional email override when sending an invoice payment link.
 */
class SendInvoicePaymentLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('billing.invoices.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional recipient email override for the payment link.
             *
             * @var string|null
             *
             * @example billing@freshbasket.com
             */
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }
}
