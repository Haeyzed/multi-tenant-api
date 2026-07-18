<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates partial updates to the authenticated central user profile.
 */
class UpdateProfileRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        return [
            /**
             * Display name of the user.
             * @var string|null
             * @example Jane Doe
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Contact email address; must be unique among users.
             * @var string|null
             * @example jane.doe@example.com
             */
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],

            /**
             * Contact phone number.
             * @var string|null
             * @example +1-555-0100
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            /**
             * IANA timezone identifier for the user.
             * @var string|null
             * @example America/New_York
             */
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }
}
