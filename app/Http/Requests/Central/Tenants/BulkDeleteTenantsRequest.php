<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for soft-deleting multiple central tenants.
 */
class BulkDeleteTenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenants.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant IDs to soft-delete.
             *
             * @var list<string>
             *
             * @example ["550e8400-e29b-41d4-a716-446655440000"]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],

            /**
             * A single tenant UUID.
             *
             * @var string
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'ids.*' => ['required', 'string', 'distinct', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
        ];
    }
}
