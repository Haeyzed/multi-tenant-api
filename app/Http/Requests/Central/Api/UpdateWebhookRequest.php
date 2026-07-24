<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for updating a central outbound webhook.
 */
class UpdateWebhookRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Destination URL that receives webhook deliveries.
             *
             * @var string
             *
             * @example https://hooks.example.com/webhooks/tenants
             */
            'url' => ['sometimes', 'url', 'max:2048'],

            /**
             * Event names this webhook subscribes to.
             *
             * @var list<string>
             *
             * @example ["tenant.created","tenant.updated"]
             */
            'events' => ['sometimes', 'array', 'min:1'],

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
