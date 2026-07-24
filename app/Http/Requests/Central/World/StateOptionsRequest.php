<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query filters for world state combobox options.
 */
class StateOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('world.view') ?? false;
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
             * Optional search term for filtering states by name or code.
             *
             * @var string|null
             *
             * @example Lagos
             */
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
