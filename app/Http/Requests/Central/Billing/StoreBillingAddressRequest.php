<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a tenant billing address.
 */
class StoreBillingAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('billing.addresses.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Billing contact or recipient name.
             *
             * @var string
             *
             * @example Ada Okafor
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Optional company name on the invoice.
             *
             * @var string|null
             *
             * @example FreshBasket Ltd
             */
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Address line 1.
             *
             * @var string
             *
             * @example 12 Admiralty Way
             */
            'line1' => ['required', 'string', 'max:255'],

            /**
             * Address line 2.
             *
             * @var string|null
             *
             * @example Suite 4B
             */
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * City name.
             *
             * @var string
             *
             * @example Lagos
             */
            'city' => ['required', 'string', 'max:255'],

            /**
             * State or province.
             *
             * @var string|null
             *
             * @example Lagos
             */
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Postal or ZIP code.
             *
             * @var string
             *
             * @example 100001
             */
            'postal_code' => ['required', 'string', 'max:50'],

            /**
             * ISO 3166-1 alpha-2 country code.
             *
             * @var string
             *
             * @example NG
             */
            'country' => ['required', 'string', 'size:2'],

            /**
             * Tax identification number.
             *
             * @var string|null
             *
             * @example 12345678-0001
             */
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],

            /**
             * Tax identifier type.
             *
             * @var string|null
             *
             * @example vat
             */
            'tax_type' => ['sometimes', 'nullable', 'string', 'max:50'],

            /**
             * Whether this address becomes the default billing address.
             *
             * @var bool
             *
             * @example true
             */
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
