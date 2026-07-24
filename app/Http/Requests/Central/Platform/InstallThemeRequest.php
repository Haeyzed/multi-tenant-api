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
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
        ];
    }
}
