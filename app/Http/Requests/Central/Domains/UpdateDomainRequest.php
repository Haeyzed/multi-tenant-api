<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Domains;

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Models\Central\Domain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating an existing tenant domain.
 */
class UpdateDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return $this->user()?->can('update', $domain) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return [
            /**
             * Fully qualified domain hostname.
             * @var string
             * @example shop.freshbasket.test
             */
            'domain' => ['sometimes', 'string', 'max:255', Rule::unique('domains', 'domain')->ignore($domain->id)],

            /**
             * Domain classification.
             * @var string
             * @example custom
             */
            'type' => ['sometimes', Rule::enum(DomainType::class)],

            /**
             * Domain lifecycle status.
             * @var string
             * @example active
             */
            'status' => ['sometimes', Rule::enum(DomainStatus::class)],

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
