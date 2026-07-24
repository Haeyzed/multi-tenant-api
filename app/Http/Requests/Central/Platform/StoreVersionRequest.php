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
            /**
             * Semantic version string for the release.
             *
             * @var string
             *
             * @example 1.4.0
             */
            'version' => ['required', 'string', 'max:50', 'unique:platform_versions,version'],

            /**
             * Human-readable release notes.
             *
             * @var string|null
             *
             * @example Adds backup schedules and AI usage metering.
             */
            'release_notes' => ['sometimes', 'nullable', 'string'],

            /**
             * Migration progress or checklist state.
             *
             * @var array<string, mixed>
             *
             * @example {"central":"pending","tenant":"pending"}
             */
            'migration_status' => ['sometimes', 'array'],

            /**
             * Arbitrary version metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"channel":"stable"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
