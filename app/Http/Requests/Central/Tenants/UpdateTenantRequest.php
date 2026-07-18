<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating an existing central tenant.
 */
class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('update', $tenant) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            /**
             * Tenant display name.
             * @var string
             * @example FreshBasket Superstore
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * URL-friendly tenant identifier.
             * @var string
             * @example fresh-basket
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],

            /**
             * Primary contact email address.
             * @var string|null
             * @example owner@freshbasket.test
             */
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],

            /**
             * Primary contact phone number.
             * @var string|null
             * @example +15551234567
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            /**
             * Optional marketing/ops tags.
             * @var list<string>
             * @example ["saas","retail"]
             */
            'tags' => ['sometimes', 'array'],

            /**
             * Single tag value.
             * @var string
             * @example retail
             */
            'tags.*' => ['string', 'max:50'],

            /**
             * Arbitrary tenant metadata key-value pairs.
             * @var array<string, mixed>
             * @example {"industry":"grocery","region":"us-east"}
             */
            'metadata' => ['sometimes', 'array'],

            /**
             * Trial expiration timestamp.
             * @var string|null
             * @example 2026-08-01T00:00:00Z
             */
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
