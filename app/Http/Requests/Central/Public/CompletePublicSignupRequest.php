<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for completing signup after card verification.
 */
class CompletePublicSignupRequest extends FormRequest
{
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
            'signup_intent_id' => ['required', 'uuid', 'exists:signup_intents,id'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trxref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
