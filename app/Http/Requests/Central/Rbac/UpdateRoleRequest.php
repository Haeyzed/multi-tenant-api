<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Models\Central\Role;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating an existing central RBAC role.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()?->can('update', $role) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            /**
             * Unique role name for the central guard.
             * @var string
             * @example support-lead
             */
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', PermissionCatalog::GUARD)
                    ->ignore($role->id),
            ],

            /**
             * Permission names to sync onto the role.
             * @var list<string>
             * @example ["tenants.view","users.view"]
             */
            'permissions' => ['sometimes', 'array'],

            /**
             * Single permission name.
             * @var string
             * @example tenants.view
             */
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', PermissionCatalog::GUARD)],
        ];
    }
}
