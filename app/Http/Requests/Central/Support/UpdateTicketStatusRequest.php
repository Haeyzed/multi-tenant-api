<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use App\Enums\Central\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating a support ticket's status.
 */
class UpdateTicketStatusRequest extends FormRequest
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
             * New lifecycle status for the ticket.
             *
             * @var string
             *
             * @example resolved
             */
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ];
    }
}
