<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use App\Enums\Central\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates public self-serve tenant signup payloads.
 */
class StorePublicSignupRequest extends FormRequest
{
    /**
     * Public signup is unauthenticated.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Organization / tenant display name.
             *
             * @var string
             *
             * @example Acme Commerce
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Owner email used as the tenant contact and login identity.
             *
             * @var string
             *
             * @example owner@acme.test
             */
            'email' => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')],

            /**
             * Owner password for immediate tenant-domain login.
             *
             * @var string
             *
             * @example Password1!
             */
            'password' => ['required', 'confirmed', Password::defaults()],

            /**
             * Publicly visible plan to start on trial.
             *
             * @var int
             *
             * @example 1
             */
            'plan_id' => ['required', 'integer', 'exists:plans,id'],

            /**
             * Optional URL-safe tenant slug.
             *
             * @var string
             *
             * @example acme-commerce
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')],

            /**
             * Optional contact phone number.
             *
             * @var string|null
             *
             * @example +15551234567
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            /**
             * Optional primary domain hostname.
             *
             * @var string|null
             *
             * @example acme.example.test
             */
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('domains', 'domain')],

            /**
             * Optional owner display name.
             *
             * @var string|null
             *
             * @example Jane Owner
             */
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Billing country ISO2 code (drives currency).
             *
             * @var string
             *
             * @example NG
             */
            'country' => ['required', 'string', 'size:2'],

            /**
             * Customer-selected payment provider for card verification.
             *
             * @var string
             *
             * @example paystack
             */
            'gateway' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('central.public.signup.setup')),
                'nullable',
                'string',
                Rule::in([
                    PaymentGateway::PAYSTACK->value,
                    PaymentGateway::FLUTTERWAVE->value,
                    PaymentGateway::STRIPE->value,
                ]),
            ],

            /**
             * Optional billing interval override.
             *
             * @var string|null
             *
             * @example monthly
             */
            'billing_interval' => ['sometimes', 'nullable', 'string', 'in:monthly,quarterly,yearly'],

            /**
             * Optional billing address fields collected at signup.
             *
             * @var array<string, mixed>|null
             */
            'billing_address' => ['sometimes', 'nullable', 'array'],
            'billing_address.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'billing_address.tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_address.tax_type' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country')) {
            $this->merge([
                'country' => strtoupper(trim((string) $this->input('country'))),
            ]);
        }

        if ($this->has('gateway')) {
            $this->merge([
                'gateway' => strtolower(trim((string) $this->input('gateway'))),
            ]);
        }
    }
}
