<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for rolling back the current release to a previous version.
 */
class RollbackVersionRequest extends FormRequest
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
            /**
             * Platform version identifier to roll back to.
             *
             * @var int
             *
             * @example 12
             */
            'target_version_id' => ['required', 'integer', 'exists:platform_versions,id'],
        ];
    }
}
