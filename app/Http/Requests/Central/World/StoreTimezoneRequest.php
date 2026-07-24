<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a timezone.
 */
class StoreTimezoneRequest extends FormRequest
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
             * IANA timezone name.
             *
             * @var string
             *
             * @example Africa/Lagos
             */
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
