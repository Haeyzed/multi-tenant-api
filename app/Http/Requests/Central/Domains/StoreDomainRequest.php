<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Domains;

use App\Enums\Central\DomainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for attaching a domain to a tenant.
 */
class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('domains.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Fully qualified domain hostname.
             * @var string
             * @example shop.freshbasket.test
             */
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')],

            /**
             * Domain classification.
             * @var string
             * @example custom
             */
            'type' => ['sometimes', Rule::enum(DomainType::class)],

            /**
             * Whether this domain is the tenant's primary hostname.
             * @var bool
             * @example true
             */
            'is_primary' => ['sometimes', 'boolean'],

            /**
             * Whether HTTP requests should redirect elsewhere.
             * @var bool
             * @example false
             */
            'is_redirect' => ['sometimes', 'boolean'],

            /**
             * Target hostname when redirect is enabled.
             * @var string|null
             * @example www.freshbasket.com
             */
            'redirect_to' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Whether HTTPS should be enforced for this domain.
             * @var bool
             * @example true
             */
            'force_https' => ['sometimes', 'boolean'],
        ];
    }
}
