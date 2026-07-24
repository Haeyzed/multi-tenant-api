<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query filters for world state combobox options.
 */
class StateOptionsRequest extends FormRequest
{
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
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
