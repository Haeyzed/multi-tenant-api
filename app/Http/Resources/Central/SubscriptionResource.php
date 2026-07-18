<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant subscription.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Subscription primary key.
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
             * Subscribed plan ID.
             *
             * @var int
             */
            'plan_id' => $this->plan_id,

            /**
             * Locked-in plan price ID.
             *
             * @var int|null
             */
            'plan_price_id' => $this->plan_price_id,

            /**
             * Subscription status value.
             *
             * @var string|null
             *
             * @example active
             */
            'status' => $this->status?->value,

            /**
             * Human-readable subscription status label.
             *
             * @var string|null
             */
            'status_label' => $this->status?->label(),

            /**
             * Billing interval value.
             *
             * @var string|null
             *
             * @example monthly
             */
            'billing_interval' => $this->billing_interval?->value,

            /**
             * Locked-in subscription price as a decimal string.
             *
             * @var string
             *
             * @example 29.00
             */
            'price' => $this->price,

            /**
             * ISO 4217 currency code.
             *
             * @var string
             *
             * @example USD
             */
            'currency' => $this->currency,

            /**
             * Payment gateway value.
             *
             * @var string|null
             *
             * @example stripe
             */
            'gateway' => $this->gateway?->value,

            /**
             * Trial period end timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'trial_ends_at' => $this->trial_ends_at,

            /**
             * Subscription start timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'starts_at' => $this->starts_at,

            /**
             * Current billing period start timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'current_period_start' => $this->current_period_start,

            /**
             * Current billing period end timestamp.
             *
             * @var string|null
             *
             * @format date-time
             */
            'current_period_end' => $this->current_period_end,

            /**
             * Pause timestamp, if paused.
             *
             * @var string|null
             *
             * @format date-time
             */
            'paused_at' => $this->paused_at,

            /**
             * Cancellation timestamp, if cancelled.
             *
             * @var string|null
             *
             * @format date-time
             */
            'cancelled_at' => $this->cancelled_at,

            /**
             * Whether cancellation takes effect at period end.
             *
             * @var bool
             */
            'cancel_at_period_end' => $this->cancel_at_period_end,

            /**
             * Grace period end timestamp after failed payment.
             *
             * @var string|null
             *
             * @format date-time
             */
            'grace_ends_at' => $this->grace_ends_at,

            /**
             * Whether the subscription is currently in a grace period.
             *
             * @var bool
             */
            'is_in_grace_period' => $this->isInGracePeriod(),

            /**
             * Reason provided when the subscription was cancelled.
             *
             * @var string|null
             */
            'cancellation_reason' => $this->cancellation_reason,

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
             * Related plan when eager-loaded.
             *
             * @var PlanResource|null
             */
            'plan' => $this->whenLoaded('plan', fn () => $this->plan
                ? new PlanResource($this->plan)
                : null),

            /**
             * Related plan price when eager-loaded.
             *
             * @var PlanPriceResource|null
             */
            'plan_price' => $this->whenLoaded('planPrice', fn () => $this->planPrice
                ? new PlanPriceResource($this->planPrice)
                : null),

            /**
             * Subscription history entries when eager-loaded.
             *
             * @var list<SubscriptionHistoryResource>|null
             */
            'histories' => SubscriptionHistoryResource::collection($this->whenLoaded('histories')),

            /**
             * Related invoices when eager-loaded.
             *
             * @var list<InvoiceResource>|null
             */
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),

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

            /**
             * Last update timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
