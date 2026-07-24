<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a central outbound webhook.
 */
class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('api.webhooks.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
