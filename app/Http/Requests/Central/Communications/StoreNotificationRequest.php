<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a central platform notification.
 */
class StoreNotificationRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'target_user_ids' => ['sometimes', 'nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
