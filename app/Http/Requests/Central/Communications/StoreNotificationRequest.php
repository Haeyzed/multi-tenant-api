<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a central platform notification.
 */
class StoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Notification headline.
             *
             * @var string
             *
             * @example New billing feature available
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * Notification body content.
             *
             * @var string
             *
             * @example You can now export invoices as CSV from the billing dashboard.
             */
            'body' => ['required', 'string'],

            /**
             * Delivery channels for the notification.
             *
             * @var list<string>
             *
             * @example ["mail", "database"]
             */
            'channels' => ['sometimes', 'array', 'min:1'],

            /**
             * A single delivery channel name.
             *
             * @var string
             *
             * @example mail
             */
            'channels.*' => ['string'],

            /**
             * Optional send time; omit to send immediately.
             *
             * @var string|null
             *
             * @example 2026-08-01T14:30:00Z
             */
            'scheduled_at' => ['sometimes', 'nullable', 'date'],

            /**
             * User IDs to receive the notification.
             *
             * @var list<int>|null
             *
             * @example [1, 42]
             */
            'target_user_ids' => ['sometimes', 'nullable', 'array'],

            /**
             * A single target user identifier.
             *
             * @var int
             *
             * @example 42
             */
            'target_user_ids.*' => ['integer', 'exists:users,id'],

            /**
             * Arbitrary notification metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"campaign":"feature-launch"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
