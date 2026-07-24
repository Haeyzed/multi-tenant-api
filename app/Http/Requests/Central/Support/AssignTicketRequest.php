<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for assigning a support ticket to a staff user.
 */
class AssignTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('support.tickets.assign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Staff user the ticket should be assigned to.
             *
             * @var int
             *
             * @example 4
             */
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
