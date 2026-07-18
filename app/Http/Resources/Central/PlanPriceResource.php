<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\PlanPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanPrice
 */
class PlanPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval?->value,
            'billing_interval_label' => $this->billing_interval?->label(),
            'trial_days' => $this->trial_days,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
