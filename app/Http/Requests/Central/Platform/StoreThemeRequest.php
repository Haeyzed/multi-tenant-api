<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use App\Enums\Central\ThemeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a theme entry.
 */
class StoreThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('themes.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Theme display name.
             *
             * @var string
             *
             * @example Aurora Storefront
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly theme slug.
             *
             * @var string
             *
             * @example aurora-storefront
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:themes,slug'],

            /**
             * Short theme description.
             *
             * @var string|null
             *
             * @example A clean commerce theme with dark mode support.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Theme package version string.
             *
             * @var string
             *
             * @example 2.0.1
             */
            'version' => ['sometimes', 'string'],

            /**
             * Theme review and publication status.
             *
             * @var string
             *
             * @example draft
             */
            'status' => ['sometimes', Rule::enum(ThemeStatus::class)],

            /**
             * Public preview image or demo URL.
             *
             * @var string|null
             *
             * @example https://cdn.example.test/themes/aurora/preview.png
             */
            'preview_url' => ['sometimes', 'nullable', 'url'],

            /**
             * Marketplace price in major currency units.
             *
             * @var float
             *
             * @example 29.00
             */
            'price' => ['sometimes', 'numeric', 'min:0'],

            /**
             * Theme author or studio name.
             *
             * @var string|null
             *
             * @example Pixel Studio
             */
            'author' => ['sometimes', 'nullable', 'string'],

            /**
             * Arbitrary theme metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"supports_dark_mode":true}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
