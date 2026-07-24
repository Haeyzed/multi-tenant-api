<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a support ticket.
 */
class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('support.tickets.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Short summary of the ticket.
             *
             * @var string
             *
             * @example Unable to access billing dashboard
             */
            'subject' => ['sometimes', 'string', 'max:255'],

            /**
             * Full description of the issue.
             *
             * @var string
             *
             * @example I get a 500 error when opening the billing dashboard.
             */
            'description' => ['sometimes', 'string'],

            /**
             * Category the ticket belongs to.
             *
             * @var int|null
             *
             * @example 3
             */
            'ticket_category_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_categories,id'],

            /**
             * Arbitrary ticket metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"source":"email"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
