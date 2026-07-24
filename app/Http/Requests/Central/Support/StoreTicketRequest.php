<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use App\Enums\Central\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a support ticket.
 */
class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('support.tickets.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant the ticket belongs to, if any.
             *
             * @var string|null
             *
             * @example 01j9x8z2f7m8f9c9c9c9c9c9c9
             */
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],

            /**
             * Category the ticket belongs to.
             *
             * @var int|null
             *
             * @example 3
             */
            'ticket_category_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_categories,id'],

            /**
             * Short summary of the ticket.
             *
             * @var string
             *
             * @example Unable to access billing dashboard
             */
            'subject' => ['required', 'string', 'max:255'],

            /**
             * Full description of the issue.
             *
             * @var string
             *
             * @example I get a 500 error when opening the billing dashboard.
             */
            'description' => ['required', 'string'],

            /**
             * Ticket priority level.
             *
             * @var string
             *
             * @example medium
             */
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],

            /**
             * Staff user the ticket should be assigned to.
             *
             * @var int|null
             *
             * @example 4
             */
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],

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
