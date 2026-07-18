<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Enums\Central\UserStatus;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates payload for creating a new central user.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * User display name.
             * @var string
             * @example Jane Admin
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Unique login email address.
             * @var string
             * @example jane.admin@example.com
             */
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],

            /**
             * Initial account password and confirmation.
             * @var string
             * @example SecurePass123!
             */
            'password' => ['sometimes', 'confirmed', Password::defaults()],

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
             * Role names to assign on creation.
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
             * Direct permission names to assign on creation.
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
