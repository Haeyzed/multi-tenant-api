<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Rbac;

use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a central RBAC role.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Unique role name for the central guard.
             * @var string
             * @example support-lead
             */
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', PermissionCatalog::GUARD)],

            /**
             * Permission names to attach on creation.
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
