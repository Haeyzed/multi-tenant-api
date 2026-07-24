<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a central API client.
 */
class UpdateApiClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('api.clients.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * API client display name.
             *
             * @var string
             *
             * @example Mobile App Client
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Permission scopes granted to the client.
             *
             * @var list<string>
             *
             * @example ["tenants.read","billing.read"]
             */
            'scopes' => ['sometimes', 'array'],

            /**
             * Maximum requests allowed per minute.
             *
             * @var int
             *
             * @example 120
             */
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:10000'],

            /**
             * Whether the API client is active.
             *
             * @var bool
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],

            /**
             * Arbitrary client metadata.
             *
             * @var array<string, mixed>
             *
             * @example {"owner":"platform","environment":"production"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
