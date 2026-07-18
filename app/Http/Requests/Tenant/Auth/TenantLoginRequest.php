<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates tenant user login credentials.
 */
class TenantLoginRequest extends FormRequest
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
             * Tenant user login email address.
             * @var string
             * @example user@tenant.example.com
             */
            'email' => ['required', 'email'],

            /**
             * Account password.
             * @var string
             * @example SecurePass123!
             */
            'password' => ['required', 'string'],
        ];
    }
}
