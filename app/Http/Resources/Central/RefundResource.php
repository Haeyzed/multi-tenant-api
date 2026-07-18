<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a payment refund.
 *
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Refund primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Associated payment ID.
             *
             * @var int
             */
            'payment_id' => $this->payment_id,

            /**
             * Owning tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,

            /**
             * Refunded amount as a decimal string.
             *
             * @var string
             *
             * @example 10.00
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
             * Refund status value.
             *
             * @var string|null
             *
             * @example completed
             */
            'status' => $this->status?->value,

            /**
             * Payment gateway reference identifier.
             *
             * @var string|null
             */
            'gateway_reference' => $this->gateway_reference,

            /**
             * Reason provided for the refund.
             *
             * @var string|null
             */
            'reason' => $this->reason,

            /**
             * Refund completion timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'refunded_at' => $this->refunded_at,

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
