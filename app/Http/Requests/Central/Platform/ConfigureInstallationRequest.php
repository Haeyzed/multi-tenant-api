<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating configuration of an installed integration.
 */
class ConfigureInstallationRequest extends FormRequest
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
            'configuration' => ['required', 'array'],
        ];
    }
}
