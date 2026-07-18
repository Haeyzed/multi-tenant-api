<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation of a central personal access token.
 */
class CreatePersonalAccessTokenRequest extends FormRequest
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
             * Human-readable name for the personal access token.
             * @var string
             * @example CI Pipeline
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Optional list of token abilities or scopes.
             * @var list<string>|null
             * @example ["read", "write"]
             */
            'abilities' => ['sometimes', 'array'],

            /**
             * Individual ability granted to the personal access token.
             * @var string
             * @example read
             */
            'abilities.*' => ['string', 'max:255'],
        ];
    }
}
