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
            'provider' => ['required', Rule::enum(AIProvider::class)],
            'label' => ['sometimes', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'api_key' => ['sometimes', 'nullable', 'string'],
            'default_model' => ['sometimes', 'nullable', 'string'],
            'monthly_token_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'credits_remaining' => ['sometimes', 'numeric', 'min:0'],
            'config' => ['sometimes', 'array'],
        ];
    }
}
