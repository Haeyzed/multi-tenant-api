<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use App\Enums\Central\ThemeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a theme entry.
 */
class StoreThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('themes.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:themes,slug'],
            'description' => ['sometimes', 'nullable', 'string'],
            'version' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(ThemeStatus::class)],
            'preview_url' => ['sometimes', 'nullable', 'url'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'author' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
