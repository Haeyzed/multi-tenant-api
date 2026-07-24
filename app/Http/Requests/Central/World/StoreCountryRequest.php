<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a country.
 */
class StoreCountryRequest extends FormRequest
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
             * Country display name.
             *
             * @var string
             *
             * @example Nigeria
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * ISO 3166-1 alpha-2 country code.
             *
             * @var string
             *
             * @example NG
             */
            'iso2' => ['required', 'string', 'size:2'],

            /**
             * ISO 3166-1 alpha-3 country code.
             *
             * @var string|null
             *
             * @example NGA
             */
            'iso3' => ['sometimes', 'nullable', 'string', 'size:3'],

            /**
             * Active status flag (0 inactive, 1 active).
             *
             * @var int
             *
             * @example 1
             */
            'status' => ['sometimes', 'integer', 'in:0,1'],

            /**
             * International dialing code.
             *
             * @var string|null
             *
             * @example 234
             */
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:5'],

            /**
             * Native-language country name.
             *
             * @var string|null
             *
             * @example Nigeria
             */
            'native' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Geographic region name.
             *
             * @var string|null
             *
             * @example Africa
             */
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Geographic subregion name.
             *
             * @var string|null
             *
             * @example Western Africa
             */
            'subregion' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Latitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 9.0820
             */
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Longitude as a string coordinate.
             *
             * @var string|null
             *
             * @example 8.6753
             */
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Country emoji flag character.
             *
             * @var string|null
             *
             * @example 🇳🇬
             */
            'emoji' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Unicode code points for the emoji flag.
             *
             * @var string|null
             *
             * @example U+1F1F3 U+1F1EC
             */
            'emojiU' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
