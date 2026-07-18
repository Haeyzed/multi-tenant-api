<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for replacing a tenant's metadata object.
 */
class UpdateTenantMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('manageMetadata', $tenant) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Complete metadata object to persist on the tenant.
             * @var array<string, mixed>
             * @example {"industry":"grocery","region":"us-east","onboarding_step":3}
             */
            'metadata' => ['required', 'array'],
        ];
    }
}
