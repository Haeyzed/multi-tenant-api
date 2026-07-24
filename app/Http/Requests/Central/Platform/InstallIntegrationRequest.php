<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for installing an integration for a tenant or centrally.
 */
class InstallIntegrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant to install for; omit for a central installation.
             *
             * @var string|null
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],

            /**
             * Initial configuration values for the installation.
             *
             * @var array<string, mixed>
             *
             * @example {"api_key":"sk_test_xxx"}
             */
            'configuration' => ['sometimes', 'array'],
        ];
    }
}
