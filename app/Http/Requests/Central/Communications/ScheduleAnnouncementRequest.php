<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for scheduling a central announcement.
 */
class ScheduleAnnouncementRequest extends FormRequest
{
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
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}
