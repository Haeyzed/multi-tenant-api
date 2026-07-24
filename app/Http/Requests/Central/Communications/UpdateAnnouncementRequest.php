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
    /**
     * Determine if the user is authorized to make this request.
     */
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
            /**
             * Announcement headline shown to recipients.
             *
             * @var string
             *
             * @example Updated maintenance window
             */
            'title' => ['sometimes', 'string', 'max:255'],

            /**
             * Full announcement body content.
             *
             * @var string
             *
             * @example Maintenance has been rescheduled to Sunday 01:00–03:00 UTC.
             */
            'body' => ['sometimes', 'string'],

            /**
             * Announcement category type.
             *
             * @var string
             *
             * @example maintenance
             */
            'type' => ['sometimes', Rule::enum(AnnouncementType::class)],

            /**
             * Audience targeting mode.
             *
             * @var string
             *
             * @example specific_plans
             */
            'target' => ['sometimes', Rule::enum(AnnouncementTarget::class)],

            /**
             * Whether recipients can dismiss the announcement.
             *
             * @var bool
             *
             * @example false
             */
            'is_dismissible' => ['sometimes', 'boolean'],

            /**
             * Plan IDs when targeting specific plans.
             *
             * @var list<int>|null
             *
             * @example [1, 3]
             */
            'target_plan_ids' => ['sometimes', 'nullable', 'array'],

            /**
             * Tenant IDs when targeting specific tenants.
             *
             * @var list<string>|null
             *
             * @example ["550e8400-e29b-41d4-a716-446655440000"]
             */
            'target_tenant_ids' => ['sometimes', 'nullable', 'array'],

            /**
             * Region codes when targeting specific regions.
             *
             * @var list<string>|null
             *
             * @example ["NG", "GH"]
             */
            'regions' => ['sometimes', 'nullable', 'array'],

            /**
             * Optional visibility start time.
             *
             * @var string|null
             *
             * @example 2026-08-01T09:00:00Z
             */
            'starts_at' => ['sometimes', 'nullable', 'date'],

            /**
             * Optional visibility end time.
             *
             * @var string|null
             *
             * @example 2026-08-15T23:59:59Z
             */
            'ends_at' => ['sometimes', 'nullable', 'date'],

            /**
             * Arbitrary announcement metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"severity":"P1"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
