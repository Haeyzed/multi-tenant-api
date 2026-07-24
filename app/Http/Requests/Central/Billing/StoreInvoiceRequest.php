<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a central billing invoice.
 */
class StoreInvoiceRequest extends FormRequest
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
             * Tenant UUID the invoice belongs to.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],

            /**
             * Optional subscription linked to the invoice.
             *
             * @var int|null
             *
             * @example 42
             */
            'subscription_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('subscriptions', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id'))),
            ],

            /**
             * Optional billing address used on the invoice.
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
             * Tax rate percentage applied to line items.
             *
             * @var float
             *
             * @example 7.5
             */
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],

            /**
             * Tax identification number shown on the invoice.
             *
             * @var string|null
             *
             * @example 12345678-0001
             */
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example NGN
             */
            'currency' => ['sometimes', 'string', 'size:3'],

            /**
             * Optional invoice notes.
             *
             * @var string|null
             *
             * @example Thank you for your business.
             */
            'notes' => ['sometimes', 'nullable', 'string'],

            /**
             * Invoice line items.
             *
             * @var list<array{description: string, quantity?: int, unit_price: float}>
             *
             * @example [{"description":"Pro Plan","quantity":1,"unit_price":49.99}]
             */
            'items' => ['required', 'array', 'min:1'],

            /**
             * Line item description.
             *
             * @var string
             *
             * @example Pro Plan
             */
            'items.*.description' => ['required', 'string'],

            /**
             * Line item quantity.
             *
             * @var int
             *
             * @example 1
             */
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],

            /**
             * Line item unit price.
             *
             * @var float
             *
             * @example 49.99
             */
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
