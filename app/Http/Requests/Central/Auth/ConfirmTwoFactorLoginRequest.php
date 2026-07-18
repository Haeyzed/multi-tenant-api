<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates two-factor authentication completion during central login.
 */
class ConfirmTwoFactorLoginRequest extends FormRequest
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
             * Temporary token issued after primary credentials are verified.
             * @var string
             * @example eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
             */
            'two_factor_token' => ['required', 'string'],

            /**
             * Six-digit authenticator app code.
             * @var string|null
             * @example 123456
             */
            'two_factor_code' => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],

            /**
             * Single-use recovery code when the authenticator app is unavailable.
             * @var string|null
             * @example abcd-efgh-ijkl
             */
            'recovery_code' => ['required_without:two_factor_code', 'nullable', 'string', 'max:20'],

            /**
             * Optional label for the device or session receiving the access token.
             * @var string|null
             * @example MacBook Pro
             */
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
