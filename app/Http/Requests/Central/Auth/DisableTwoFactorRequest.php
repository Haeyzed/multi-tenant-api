<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates password confirmation when disabling two-factor authentication.
 */
class DisableTwoFactorRequest extends FormRequest
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
             * Current account password for verification.
             * @var string
             * @example SecurePass123!
             */
            'password' => ['required', 'string'],
        ];
    }
}
