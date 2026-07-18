<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates payload for resetting a central user's password.
 */
class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('resetPassword', $user) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * New password and confirmation.
             * @var string
             * @example NewSecurePass456!
             */
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ];
    }
}
