<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\BillingAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a tenant billing address.
 *
 * @mixin BillingAddress
 */
class BillingAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Billing address primary key.
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
             * Recipient or contact name.
             *
             * @var string
             *
             * @example Jane Doe
             */
            'name' => $this->name,

            /**
             * Company name, if applicable.
             *
             * @var string|null
             *
             * @example Acme Corp
             */
            'company' => $this->company,

            /**
             * First address line.
             *
             * @var string
             *
             * @example 123 Main St
             */
            'line1' => $this->line1,

            /**
             * Second address line.
             *
             * @var string|null
             */
            'line2' => $this->line2,

            /**
             * City name.
             *
             * @var string
             *
             * @example Springfield
             */
            'city' => $this->city,

            /**
             * State or province.
             *
             * @var string|null
             *
             * @example IL
             */
            'state' => $this->state,

            /**
             * Postal or ZIP code.
             *
             * @var string
             *
             * @example 62701
             */
            'postal_code' => $this->postal_code,

            /**
             * ISO 3166-1 alpha-2 country code.
             *
             * @var string
             *
             * @example US
             */
            'country' => $this->country,

            /**
             * Tax identification number.
             *
             * @var string|null
             */
            'tax_id' => $this->tax_id,

            /**
             * Tax ID type or scheme.
             *
             * @var string|null
             */
            'tax_type' => $this->tax_type,

            /**
             * Whether this is the tenant's default billing address.
             *
             * @var bool
             */
            'is_default' => $this->is_default,
        ];
    }
}
