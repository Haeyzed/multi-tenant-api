<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates central user login credentials and optional two-factor fields.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * User login email address.
             * @var string
             * @example admin@example.com
             */
            'email' => ['required', 'email'],

            /**
             * Account password.
             * @var string
             * @example SecurePass123!
             */
            'password' => ['required', 'string'],

            /**
             * Optional label for the device or session receiving the access token.
             * @var string|null
             * @example iPhone 15
             */
            'device_name' => ['sometimes', 'string', 'max:255'],

            /**
             * Six-digit authenticator app code when two-factor authentication is enabled.
             * @var string|null
             * @example 123456
             */
            'two_factor_code' => ['sometimes', 'string', 'size:6'],

            /**
             * Single-use recovery code when the authenticator app is unavailable.
             * @var string|null
             * @example abcd-efgh-ijkl
             */
            'recovery_code' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
