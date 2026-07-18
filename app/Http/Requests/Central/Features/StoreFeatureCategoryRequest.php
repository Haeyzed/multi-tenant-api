<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Features;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a feature category.
 */
class StoreFeatureCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('features.manage-categories') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Category display name.
             * @var string
             * @example Analytics
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly category slug.
             * @var string
             * @example analytics
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:feature_categories,slug'],

            /**
             * Optional category description.
             * @var string|null
             * @example Reporting and insights features.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Icon identifier for UI surfaces.
             * @var string|null
             * @example folder-chart
             */
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],

            /**
             * Display order within category listings.
             * @var int
             * @example 5
             */
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            /**
             * Whether the category is visible and selectable.
             * @var bool
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
