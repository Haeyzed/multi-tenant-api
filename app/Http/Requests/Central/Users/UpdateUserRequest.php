<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Enums\Central\UserStatus;
use App\Models\User;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating an existing central user.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            /**
             * User display name.
             * @var string
             * @example Jane Admin
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Login email address.
             * @var string
             * @example jane.admin@example.com
             */
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],

            /**
             * Contact phone number.
             * @var string|null
             * @example +15559876543
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            /**
             * IANA timezone identifier.
             * @var string
             * @example America/New_York
             */
            'timezone' => ['sometimes', 'timezone:all'],

            /**
             * Account lifecycle status.
             * @var string
             * @example active
             */
            'status' => ['sometimes', Rule::enum(UserStatus::class)],

            /**
             * Role names to sync onto the user.
             * @var list<string>
             * @example ["admin","support"]
             */
            'roles' => ['sometimes', 'array'],

            /**
             * Single role name.
             * @var string
             * @example admin
             */
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', PermissionCatalog::GUARD)],

            /**
             * Direct permission names to sync onto the user.
             * @var list<string>
             * @example ["tenants.view","users.create"]
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
