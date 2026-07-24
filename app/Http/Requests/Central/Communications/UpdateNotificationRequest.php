<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a central platform notification.
 */
class UpdateNotificationRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'target_user_ids' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
