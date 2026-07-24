<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Communications;

use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating a central announcement.
 */
class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('announcements.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'type' => ['sometimes', Rule::enum(AnnouncementType::class)],
            'target' => ['sometimes', Rule::enum(AnnouncementTarget::class)],
            'is_dismissible' => ['sometimes', 'boolean'],
            'target_plan_ids' => ['sometimes', 'nullable', 'array'],
            'target_tenant_ids' => ['sometimes', 'nullable', 'array'],
            'regions' => ['sometimes', 'nullable', 'array'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
