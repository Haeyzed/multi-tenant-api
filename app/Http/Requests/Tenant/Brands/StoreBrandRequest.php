<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Brands;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'logo_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'banner_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'country_of_origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
