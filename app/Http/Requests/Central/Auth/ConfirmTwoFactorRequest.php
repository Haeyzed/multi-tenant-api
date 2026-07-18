<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the authenticator code when enabling two-factor authentication.
 */
class ConfirmTwoFactorRequest extends FormRequest
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
             * Six-digit code from the authenticator app to confirm setup.
             * @var string
             * @example 123456
             */
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
