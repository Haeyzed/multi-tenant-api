<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for replying to a support ticket.
 */
class ReplyTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('support.tickets.reply') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Reply message body.
             *
             * @var string
             *
             * @example Thanks for reaching out, we're looking into this now.
             */
            'body' => ['required', 'string'],

            /**
             * Whether the reply is an internal note hidden from the customer.
             *
             * @var bool
             *
             * @example false
             */
            'is_internal' => ['sometimes', 'boolean'],

            /**
             * Optional file attachment for the reply.
             *
             * @var string|null
             *
             * @example screenshot.png
             */
            'attachment' => ['sometimes', 'file', 'max:10240'],
        ];
    }
}
