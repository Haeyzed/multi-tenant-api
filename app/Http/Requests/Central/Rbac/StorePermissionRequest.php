<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a central permission.
 */
class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Unique permission name (dot-namespaced).
             *
             * @var string
             *
             * @example reports.export
             */
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('permissions', 'name')->where('guard_name', PermissionCatalog::GUARD),
            ],
        ];
    }
}
