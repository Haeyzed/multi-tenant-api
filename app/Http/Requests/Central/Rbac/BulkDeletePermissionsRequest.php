<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for deleting multiple central permissions.
 */
class BulkDeletePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Permission IDs to delete.
             *
             * @var list<int>
             *
             * @example [10, 11]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],

            /**
             * A single permission ID.
             *
             * @var int
             *
             * @example 10
             */
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', PermissionCatalog::GUARD),
            ],
        ];
    }
}
