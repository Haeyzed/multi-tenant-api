<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for manually dispatching a webhook event delivery.
 */
class DispatchWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            /**
             * Webhook event name to dispatch.
             *
             * @var string
             *
             * @example tenant.created
             */
            'event' => ['required', 'string'],

            /**
             * Optional event payload body.
             *
             * @var array<string, mixed>
             *
             * @example {"tenant_id":"550e8400-e29b-41d4-a716-446655440000","name":"FreshBasket"}
             */
            'payload' => ['sometimes', 'array'],
        ];
    }
}
