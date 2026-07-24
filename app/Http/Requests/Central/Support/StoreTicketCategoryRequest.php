<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a support ticket category.
 */
class StoreTicketCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('support.categories.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Category display name.
             *
             * @var string
             *
             * @example Billing
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly category slug.
             *
             * @var string
             *
             * @example billing
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:ticket_categories,slug'],

            /**
             * Optional category description.
             *
             * @var string|null
             *
             * @example Issues related to invoices and payments.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Whether the category is active and selectable.
             *
             * @var bool
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],

            /**
             * Display order within category listings.
             *
             * @var int
             *
             * @example 1
             */
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
