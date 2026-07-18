<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates central user password reset with token and email verification.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Password reset token from the reset email link.
             * @var string
             * @example 8f3c2a1b9d4e7f6a5b0c1d2e3f4a5b6c
             */
            'token' => ['required', 'string'],

            /**
             * Email address the reset token was issued for.
             * @var string
             * @example admin@example.com
             */
            'email' => ['required', 'email'],

            /**
             * New password and confirmation.
             * @var string
             * @example NewSecurePass456!
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
