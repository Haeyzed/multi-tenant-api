<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant invoice.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Invoice primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Owning tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,

            /**
             * Associated subscription ID.
             *
             * @var int|null
             */
            'subscription_id' => $this->subscription_id,

            /**
             * Human-readable invoice number.
             *
             * @var string
             *
             * @example INV-2026-0001
             */
            'number' => $this->number,

            /**
             * Invoice status value.
             *
             * @var string|null
             *
             * @example paid
             */
            'status' => $this->status?->value,

            /**
             * Human-readable invoice status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Subtotal before tax as a decimal string.
             *
             * @var string
             *
             * @example 29.00
             */
            'subtotal' => $this->subtotal,

            /**
             * Applied tax rate as a decimal string.
             *
             * @var string|null
             *
             * @example 0.08
             */
            'tax_rate' => $this->tax_rate,

            /**
             * Tax amount as a decimal string.
             *
             * @var string
             *
             * @example 2.32
             */
            'tax' => $this->tax,

            /**
             * Invoice total as a decimal string.
             *
             * @var string
             *
             * @example 31.32
             */
            'total' => $this->total,

            /**
             * Amount paid so far as a decimal string.
             *
             * @var string
             *
             * @example 31.32
             */
            'amount_paid' => $this->amount_paid,

            /**
             * Remaining balance due as a decimal string.
             *
             * @var string
             */
            'balance_due' => $this->balanceDue(),

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example USD
             */
            'currency' => $this->currency,

            /**
             * Tax identification number printed on the invoice.
             *
             * @var string|null
             */
            'tax_id' => $this->tax_id,

            /**
             * Invoice issue timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'issued_at' => $this->issued_at,

            /**
             * Payment due timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'due_at' => $this->due_at,

            /**
             * Payment completion timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'paid_at' => $this->paid_at,

            /**
             * Administrator notes on the invoice.
             *
             * @var string|null
             */
            'notes' => $this->notes,

            /**
             * Related tenant summary when eager-loaded.
             *
             * @var array{id: string, name: string|null, slug: string|null}|null
             */
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
                'slug' => $this->tenant?->slug,
            ]),

            /**
             * Related subscription summary when eager-loaded.
             *
             * @var array{id: int, status: string|null, plan_name: string|null}|null
             */
            'subscription' => $this->whenLoaded('subscription', fn () => [
                'id' => $this->subscription?->id,
                'status' => $this->subscription?->status?->value,
                'plan_name' => $this->subscription?->plan?->name,
            ]),

            /**
             * Line items when eager-loaded.
             *
             * @var list<array{id: int, description: string, quantity: int, unit_price: string, total: string}>|null
             */
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])),

            /**
             * Billing address when eager-loaded.
             *
             * @var BillingAddressResource|null
             */
            'billing_address' => new BillingAddressResource($this->whenLoaded('billingAddress')),

            /**
             * Creation timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             *
             * @example 2026-07-13T11:22:26.000000Z
             */
            'created_at' => $this->created_at,
        ];
    }
}
