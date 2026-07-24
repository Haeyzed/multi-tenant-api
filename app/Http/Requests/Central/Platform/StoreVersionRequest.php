<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a draft platform version.
 */
class StoreVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('versions.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50', 'unique:platform_versions,version'],
            'release_notes' => ['sometimes', 'nullable', 'string'],
            'migration_status' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
