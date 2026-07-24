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
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'subscription_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('subscriptions', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id'))),
            ],
            'billing_address_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('billing_addresses', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id'))),
            ],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
