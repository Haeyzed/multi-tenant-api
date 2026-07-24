<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for scheduling a central platform notification.
 */
class ScheduleNotificationRequest extends FormRequest
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
             * When the notification should be sent.
             *
             * @var string
             *
             * @example 2026-08-01T14:30:00Z
             */
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
