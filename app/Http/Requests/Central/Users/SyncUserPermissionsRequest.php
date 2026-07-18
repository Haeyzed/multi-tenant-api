<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Models\User;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a full direct-permission sync payload for a central user.
 */
class SyncUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('assignPermissions', $user) ?? false;
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
             * @example ["tenants.view","users.create"]
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
