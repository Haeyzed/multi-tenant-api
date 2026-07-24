<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a marketplace integration.
 */
class StoreIntegrationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:integrations,slug'],
            'vendor' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'version' => ['sometimes', 'string'],
            'is_marketplace' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'permissions' => ['sometimes', 'array'],
            'config_schema' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
