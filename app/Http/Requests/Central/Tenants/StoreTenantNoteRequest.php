<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating an internal or public tenant note.
 */
class StoreTenantNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('manageNotes', $tenant) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Note body content.
             * @var string
             * @example Customer requested extended trial period.
             */
            'body' => ['required', 'string', 'max:5000'],

            /**
             * Whether the note is visible only to central staff.
             * @var bool
             * @example true
             */
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
