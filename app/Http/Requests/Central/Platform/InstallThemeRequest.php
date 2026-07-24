<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for installing a published theme for a tenant.
 */
class InstallThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('themes.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant that should receive the theme installation.
             *
             * @var string|null
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
        ];
    }
}
