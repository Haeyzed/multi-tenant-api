<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a language.
 */
class UpdateLanguageRequest extends FormRequest
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
             * ISO 639-1 language code.
             *
             * @var string
             *
             * @example en
             */
            'code' => ['sometimes', 'string', 'size:2'],

            /**
             * Language display name.
             *
             * @var string
             *
             * @example English
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Native-language display name.
             *
             * @var string
             *
             * @example English
             */
            'name_native' => ['sometimes', 'string', 'max:255'],

            /**
             * Text directionality (ltr or rtl).
             *
             * @var string
             *
             * @example ltr
             */
            'dir' => ['sometimes', 'string', 'in:ltr,rtl'],
        ];
    }
}
