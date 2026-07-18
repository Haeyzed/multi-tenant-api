<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant payment.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Payment primary key.
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
             * Associated invoice ID.
             *
             * @var int|null
             */
            'invoice_id' => $this->invoice_id,

            /**
             * Associated subscription ID.
             *
             * @var int|null
             */
            'subscription_id' => $this->subscription_id,

            /**
             * Payment gateway value.
             *
             * @var string|null
             *
             * @example stripe
             */
            'gateway' => $this->gateway?->value,

            /**
             * Human-readable payment gateway label.
             *
             * @var string|null
             */
            'gateway_label' => $this->gateway?->label(),

            /**
             * Payment status value.
             *
             * @var string|null
             *
             * @example completed
             */
            'status' => $this->status?->value,

            /**
             * Human-readable payment status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Payment amount as a decimal string.
             *
             * @var string
             *
             * @example 29.00
             */
            'amount' => $this->amount,

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example USD
             */
            'currency' => $this->currency,

            /**
             * Payment gateway reference identifier.
             *
             * @var string|null
             */
            'gateway_reference' => $this->gateway_reference,

            /**
             * Failure reason when payment did not succeed.
             *
             * @var string|null
             */
            'failure_reason' => $this->failure_reason,

            /**
             * Checkout or authorization URL from the latest attempt when attempts are eager-loaded.
             *
             * @var string|null
             */
            'checkout_url' => $this->whenLoaded('attempts', function () {
                $payload = $this->attempts->last()?->payload ?? [];

                return $payload['checkout_url'] ?? $payload['authorization_url'] ?? null;
            }),

            /**
             * Payment completion timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'paid_at' => $this->paid_at,

            /**
             * Total refunded amount when refunds are eager-loaded.
             *
             * @var string|null
             */
            'refunded_amount' => $this->whenLoaded('refunds', fn () => $this->refundedAmount()),

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
             * Related invoice summary when eager-loaded.
             *
             * @var array{id: int, number: string|null, status: string|null}|null
             */
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice?->id,
                'number' => $this->invoice?->number,
                'status' => $this->invoice?->status?->value,
            ]),

            /**
             * Payment attempt history when eager-loaded.
             *
             * @var list<array{id: int, attempt_number: int, status: string|null, gateway_reference: string|null, response_message: string|null, created_at: string|null}>|null
             */
            'attempts' => $this->whenLoaded('attempts', fn () => $this->attempts->map(fn ($a) => [
                'id' => $a->id,
                'attempt_number' => $a->attempt_number,
                'status' => $a->status?->value,
                'gateway_reference' => $a->gateway_reference,
                'response_message' => $a->response_message,
                'created_at' => $a->created_at,
            ])),

            /**
             * Refunds when eager-loaded.
             *
             * @var list<RefundResource>|null
             */
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),

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
