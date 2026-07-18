<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Models\Central\Role;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a full permission sync payload for a central RBAC role.
 */
class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()?->can('assignPermissions', $role) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Complete list of permission names to assign.
             * @var list<string>
             * @example ["tenants.view","users.view","plans.manage"]
             */
            'permissions' => ['required', 'array'],

            /**
             * Single permission name.
             * @var string
             * @example tenants.view
             */
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', PermissionCatalog::GUARD)],
        ];
    }
}
