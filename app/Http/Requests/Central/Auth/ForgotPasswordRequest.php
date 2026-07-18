<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a forgot-password email submission for central users.
 */
class ForgotPasswordRequest extends FormRequest
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
             * Email address associated with the account requesting a reset link.
             * @var string
             * @example admin@example.com
             */
            'email' => ['required', 'email'],
        ];
    }
}
