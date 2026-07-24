<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates optional email override when sending an invoice payment link.
 */
class SendInvoicePaymentLinkRequest extends FormRequest
{
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
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }
}
