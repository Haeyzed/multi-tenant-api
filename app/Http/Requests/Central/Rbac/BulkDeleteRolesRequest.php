<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for deleting multiple central roles.
 */
class BulkDeleteRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Role IDs to delete.
             *
             * @var list<int>
             *
             * @example [2, 3]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],

            /**
             * A single role ID.
             *
             * @var int
             *
             * @example 2
             */
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where('guard_name', PermissionCatalog::GUARD),
            ],
        ];
    }
}
