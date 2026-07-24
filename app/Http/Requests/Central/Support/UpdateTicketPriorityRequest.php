<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use App\Enums\Central\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating a support ticket's priority.
 */
class UpdateTicketPriorityRequest extends FormRequest
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
             * New priority level for the ticket, adjusting its SLA target.
             *
             * @var string
             *
             * @example high
             */
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ];
    }
}
