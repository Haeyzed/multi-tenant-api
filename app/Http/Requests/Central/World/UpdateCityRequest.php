<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a city.
 */
class UpdateCityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('world.update') ?? false;
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
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],

            /**
             * Parent state identifier.
             *
             * @var int
             *
             * @example 10
             */
            'state_id' => ['sometimes', 'integer', 'exists:states,id'],

            /**
             * City display name.
             *
             * @var string
             *
             * @example Ikeja
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Country code associated with the city.
             *
             * @var string
             *
             * @example NG
             */
            'country_code' => ['sometimes', 'string', 'max:3'],

            /**
             * State subdivision code associated with the city.
             *
             * @var string
             *
             * @example LA
             */
            'state_code' => ['sometimes', 'string', 'max:5'],

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
