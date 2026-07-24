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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(BackupType::class)],
            'cron_expression' => ['sometimes', 'string'],
            'retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
