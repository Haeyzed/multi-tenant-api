<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a central outbound webhook.
 */
class StoreWebhookRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'api_client_id' => ['sometimes', 'nullable', 'integer', 'exists:api_clients,id'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
