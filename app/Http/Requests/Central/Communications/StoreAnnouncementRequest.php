<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a central announcement.
 */
class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('announcements.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'target' => ['sometimes', Rule::enum(AnnouncementTarget::class)],
            'is_dismissible' => ['sometimes', 'boolean'],
            'target_plan_ids' => ['sometimes', 'nullable', 'array'],
            'target_tenant_ids' => ['sometimes', 'nullable', 'array'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
