<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for completing signup after card verification.
 */
class CompletePublicSignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
             * Signup intent UUID created during the public signup flow.
             *
             * @var string
             *
             * @example 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
             */
            'signup_intent_id' => ['required', 'uuid', 'exists:signup_intents,id'],

            /**
             * Checkout or card-verification session identifier from the gateway.
             *
             * @var string|null
             *
             * @example cs_test_a1b2c3d4
             */
            'session_id' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Paystack transaction reference echoed from the callback.
             *
             * @var string|null
             *
             * @example T12345abcde
             */
            'trxref' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Payment gateway transaction reference.
             *
             * @var string|null
             *
             * @example T12345abcde
             */
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Gateway transaction identifier when provided as transaction_id.
             *
             * @var string|null
             *
             * @example 984321
             */
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Gateway transaction identifier when provided as id.
             *
             * @var string|null
             *
             * @example 984321
             */
            'id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
