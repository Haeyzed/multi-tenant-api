<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a state.
 */
class StoreStateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('world.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Parent country identifier.
             *
             * @var int
             *
             * @example 1
             */
            'country_id' => ['required', 'integer', 'exists:countries,id'],

            /**
             * State display name.
             *
             * @var string
             *
             * @example Lagos
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Country code associated with the state.
             *
             * @var string|null
             *
             * @example NG
             */
            'country_code' => ['sometimes', 'nullable', 'string', 'max:3'],

            /**
             * State subdivision code.
             *
             * @var string|null
             *
             * @example LA
             */
            'state_code' => ['sometimes', 'nullable', 'string', 'max:5'],

            /**
             * Subdivision type label.
             *
             * @var string|null
             *
             * @example state
             */
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Latitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 6.5244
             */
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Longitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 3.3792
             */
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
