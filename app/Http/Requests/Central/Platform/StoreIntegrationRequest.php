<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating a marketplace integration.
 */
class StoreIntegrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Integration display name.
             *
             * @var string
             *
             * @example Slack Alerts
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly integration slug.
             *
             * @var string
             *
             * @example slack-alerts
             */
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:integrations,slug'],

            /**
             * Vendor or publisher name.
             *
             * @var string|null
             *
             * @example Acme Labs
             */
            'vendor' => ['sometimes', 'nullable', 'string'],

            /**
             * Short marketplace description.
             *
             * @var string|null
             *
             * @example Send platform alerts to a Slack channel.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Integration package version string.
             *
             * @var string
             *
             * @example 1.2.0
             */
            'version' => ['sometimes', 'string'],

            /**
             * Whether the integration is listed in the marketplace.
             *
             * @var bool
             *
             * @example true
             */
            'is_marketplace' => ['sometimes', 'boolean'],

            /**
             * Marketplace price in major currency units.
             *
             * @var float
             *
             * @example 9.99
             */
            'price' => ['sometimes', 'numeric', 'min:0'],

            /**
             * Permissions requested by the integration.
             *
             * @var list<string>
             *
             * @example ["tenants.read", "billing.read"]
             */
            'permissions' => ['sometimes', 'array'],

            /**
             * JSON-schema style configuration form definition.
             *
             * @var array<string, mixed>
             *
             * @example {"type":"object","properties":{"webhook_url":{"type":"string"}}}
             */
            'config_schema' => ['sometimes', 'array'],

            /**
             * Arbitrary integration metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"category":"communication"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
