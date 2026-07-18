<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Enums\Central\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for changing a central user's status.
 */
class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('manageStatus', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * New account lifecycle status.
             * @var string
             * @example suspended
             */
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }
}
