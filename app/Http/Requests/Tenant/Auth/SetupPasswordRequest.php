<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates initial password setup for a tenant user via invitation token.
 */
class SetupPasswordRequest extends FormRequest
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
             * Invitation or setup token from the onboarding email.
             * @var string
             * @example 4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d
             */
            'token' => ['required', 'string'],

            /**
             * New account password and confirmation.
             * @var string
             * @example TenantPass789!
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
