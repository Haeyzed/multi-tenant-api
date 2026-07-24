<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for manually dispatching a webhook event delivery.
 */
class DispatchWebhookRequest extends FormRequest
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
            'event' => ['required', 'string'],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
