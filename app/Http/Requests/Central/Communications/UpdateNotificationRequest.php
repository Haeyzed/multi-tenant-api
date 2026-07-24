<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a central platform notification.
 */
class UpdateNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.update') ?? false;
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
             * @example Billing export reminder
             */
            'title' => ['sometimes', 'string', 'max:255'],

            /**
             * Notification body content.
             *
             * @var string
             *
             * @example Remember to try the new CSV invoice export.
             */
            'body' => ['sometimes', 'string'],

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
             * @example database
             */
            'channels.*' => ['string'],

            /**
             * Optional send time; omit to keep existing schedule.
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
