<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

/**
 * Validates payload for updating a central permission.
 */
class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return $this->user()?->can('update', $permission) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

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
                Rule::unique('permissions', 'name')
                    ->where('guard_name', PermissionCatalog::GUARD)
                    ->ignore($permission->id),
            ],
        ];
    }
}
