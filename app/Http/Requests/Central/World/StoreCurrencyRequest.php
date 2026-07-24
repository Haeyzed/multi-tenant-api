<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\World;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a currency.
 */
class StoreCurrencyRequest extends FormRequest
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
             * Currency display name.
             *
             * @var string
             *
             * @example Nigerian Naira
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Currency alphabetic code.
             *
             * @var string
             *
             * @example NGN
             */
            'code' => ['required', 'string', 'max:255'],

            /**
             * Decimal precision for monetary amounts.
             *
             * @var int
             *
             * @example 2
             */
            'precision' => ['sometimes', 'integer', 'min:0', 'max:8'],

            /**
             * Currency symbol.
             *
             * @var string
             *
             * @example ₦
             */
            'symbol' => ['required', 'string', 'max:255'],

            /**
             * Native currency symbol.
             *
             * @var string|null
             *
             * @example ₦
             */
            'symbol_native' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Whether the symbol appears before the amount.
             *
             * @var bool
             *
             * @example true
             */
            'symbol_first' => ['sometimes', 'boolean'],

            /**
             * Decimal separator character.
             *
             * @var string
             *
             * @example .
             */
            'decimal_mark' => ['sometimes', 'string', 'size:1'],

            /**
             * Thousands separator character.
             *
             * @var string
             *
             * @example ,
             */
            'thousands_separator' => ['sometimes', 'string', 'size:1'],
        ];
    }
}
