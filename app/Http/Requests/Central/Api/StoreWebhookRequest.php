<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a central outbound webhook.
 */
class StoreWebhookRequest extends FormRequest
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
             * Webhook display name.
             *
             * @var string
             *
             * @example Tenant Lifecycle Hook
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Destination URL that receives webhook deliveries.
             *
             * @var string
             *
             * @example https://hooks.example.com/webhooks/tenants
             */
            'url' => ['required', 'url', 'max:2048'],

            /**
             * Event names this webhook subscribes to.
             *
             * @var list<string>
             *
             * @example ["tenant.created","tenant.updated"]
             */
            'events' => ['required', 'array', 'min:1'],

            /**
             * Single subscribed event name.
             *
             * @var string
             *
             * @example tenant.created
             */
            'events.*' => ['string'],

            /**
             * Whether the webhook is active.
             *
             * @var bool
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],

            /**
             * Maximum delivery retry attempts.
             *
             * @var int
             *
             * @example 3
             */
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:10'],

            /**
             * HTTP request timeout in seconds.
             *
             * @var int
             *
             * @example 15
             */
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],

            /**
             * Optional related API client identifier.
             *
             * @var int|null
             *
             * @example 12
             */
            'api_client_id' => ['sometimes', 'nullable', 'integer', 'exists:api_clients,id'],

            /**
             * Arbitrary webhook metadata.
             *
             * @var array<string, mixed>
             *
             * @example {"team":"platform","priority":"high"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
