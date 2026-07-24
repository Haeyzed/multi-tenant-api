<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use App\Enums\Central\AIProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating or updating an AI provider setting.
 */
class UpsertAiProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('ai.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * AI provider identifier.
             *
             * @var string
             *
             * @example openai
             */
            'provider' => ['required', Rule::enum(AIProvider::class)],

            /**
             * Friendly label shown in admin UI.
             *
             * @var string
             *
             * @example OpenAI Production
             */
            'label' => ['sometimes', 'string', 'max:255'],

            /**
             * Whether this provider is available for use.
             *
             * @var bool
             *
             * @example true
             */
            'is_enabled' => ['sometimes', 'boolean'],

            /**
             * Provider API key (stored encrypted server-side).
             *
             * @var string|null
             *
             * @example sk-proj-xxxxxxxx
             */
            'api_key' => ['sometimes', 'nullable', 'string'],

            /**
             * Default model name for requests.
             *
             * @var string|null
             *
             * @example gpt-4o-mini
             */
            'default_model' => ['sometimes', 'nullable', 'string'],

            /**
             * Soft monthly token budget for this provider.
             *
             * @var int|null
             *
             * @example 1000000
             */
            'monthly_token_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Remaining credit balance for metered billing.
             *
             * @var float
             *
             * @example 25.5
             */
            'credits_remaining' => ['sometimes', 'numeric', 'min:0'],

            /**
             * Provider-specific configuration options.
             *
             * @var array<string, mixed>
             *
             * @example {"organization":"org_abc","base_url":"https://api.openai.com/v1"}
             */
            'config' => ['sometimes', 'array'],
        ];
    }
}
