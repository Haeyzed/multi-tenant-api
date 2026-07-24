<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for scheduling a central announcement.
 */
class ScheduleAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('announcements.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * When the announcement becomes visible.
             *
             * @var string
             *
             * @example 2026-08-01T09:00:00Z
             */
            'starts_at' => ['required', 'date', 'after:now'],

            /**
             * Optional end time after which the announcement is hidden.
             *
             * @var string|null
             *
             * @example 2026-08-15T23:59:59Z
             */
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}
