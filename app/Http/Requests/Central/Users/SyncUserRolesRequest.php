<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Models\User;
use App\Support\Central\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a full role sync payload for a central user.
 */
class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('assignRoles', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Complete list of role names to assign.
             * @var list<string>
             * @example ["admin","support"]
             */
            'roles' => ['required', 'array'],

            /**
             * Single role name.
             * @var string
             * @example admin
             */
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', PermissionCatalog::GUARD)],
        ];
    }
}
