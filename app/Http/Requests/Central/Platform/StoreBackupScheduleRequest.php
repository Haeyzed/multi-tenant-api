<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use App\Enums\Central\BackupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a cron-based backup schedule.
 */
class StoreBackupScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('backups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Schedule display name.
             *
             * @var string
             *
             * @example Nightly database backup
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Backup content type produced by the schedule.
             *
             * @var string
             *
             * @example database
             */
            'type' => ['sometimes', Rule::enum(BackupType::class)],

            /**
             * Cron expression controlling when backups run.
             *
             * @var string
             *
             * @example 0 2 * * *
             */
            'cron_expression' => ['sometimes', 'string'],

            /**
             * How many days to retain completed backups.
             *
             * @var int
             *
             * @example 30
             */
            'retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],

            /**
             * Whether the schedule is currently active.
             *
             * @var bool
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],

            /**
             * Arbitrary schedule metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"notify_on_failure":true}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
