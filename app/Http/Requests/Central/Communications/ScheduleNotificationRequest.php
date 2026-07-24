<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for scheduling a central platform notification.
 */
class ScheduleNotificationRequest extends FormRequest
{
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
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
