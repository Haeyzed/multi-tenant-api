<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a language.
 */
class StoreLanguageRequest extends FormRequest
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
             * ISO 639-1 language code.
             *
             * @var string
             *
             * @example en
             */
            'code' => ['required', 'string', 'size:2'],

            /**
             * Language display name.
             *
             * @var string
             *
             * @example English
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Native-language display name.
             *
             * @var string
             *
             * @example English
             */
            'name_native' => ['required', 'string', 'max:255'],

            /**
             * Text directionality (ltr or rtl).
             *
             * @var string
             *
             * @example ltr
             */
            'dir' => ['required', 'string', 'in:ltr,rtl'],
        ];
    }
}
