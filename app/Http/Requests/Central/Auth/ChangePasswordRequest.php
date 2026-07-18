<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a central user password change request.
 */
class ChangePasswordRequest extends FormRequest
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
             * Current account password for verification.
             * @var string
             * @example CurrentPass123!
             */
            'current_password' => ['required', 'string'],

            /**
             * New password and confirmation.
             * @var string
             * @example NewSecurePass456!
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
