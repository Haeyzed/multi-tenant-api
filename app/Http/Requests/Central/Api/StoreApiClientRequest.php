<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Api;

use App\Enums\Central\ApiKeyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a central API client.
 */
class StoreApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('api.clients.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ApiKeyType::class)],
            'scopes' => ['sometimes', 'array'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
