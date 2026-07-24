<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a city.
 */
class StoreCityRequest extends FormRequest
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
             * Parent state identifier.
             *
             * @var int
             *
             * @example 10
             */
            'state_id' => ['required', 'integer', 'exists:states,id'],

            /**
             * City display name.
             *
             * @var string
             *
             * @example Ikeja
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Country code associated with the city.
             *
             * @var string
             *
             * @example NG
             */
            'country_code' => ['required', 'string', 'max:3'],

            /**
             * State subdivision code associated with the city.
             *
             * @var string
             *
             * @example LA
             */
            'state_code' => ['required', 'string', 'max:5'],

            /**
             * Latitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 6.6018
             */
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Longitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 3.3515
             */
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
