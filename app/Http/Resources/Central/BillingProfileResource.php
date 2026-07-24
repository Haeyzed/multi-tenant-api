<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\BillingProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingProfile
 */
class BillingProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'country_iso2' => $this->country_iso2,
            'currency' => $this->currency,
            'preferred_gateway' => $this->preferred_gateway,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
