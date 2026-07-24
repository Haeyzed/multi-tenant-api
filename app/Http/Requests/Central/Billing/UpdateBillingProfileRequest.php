<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a tenant billing profile.
 */
class UpdateBillingProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('tenants.update') || $user?->can('billing.invoices.view')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * ISO 3166-1 alpha-2 country code for billing.
             *
             * @var string|null
             *
             * @example NG
             */
            'country_iso2' => ['sometimes', 'nullable', 'string', 'size:2'],

            /**
             * ISO 4217 preferred billing currency.
             *
             * @var string|null
             *
             * @example NGN
             */
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            /**
             * Preferred payment gateway driver.
             *
             * @var string|null
             *
             * @example paystack
             */
            'preferred_gateway' => ['sometimes', 'nullable', 'string', 'max:50'],

            /**
             * Arbitrary billing profile metadata.
             *
             * @var array<string, mixed>|null
             *
             * @example {"tax_exempt":false,"invoice_prefix":"FB"}
             */
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
