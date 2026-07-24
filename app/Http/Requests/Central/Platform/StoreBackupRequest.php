<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use App\Enums\Central\BackupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a manual backup snapshot.
 */
class StoreBackupRequest extends FormRequest
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
             * Human-readable backup label.
             *
             * @var string
             *
             * @example Pre-release snapshot
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * Backup content type.
             *
             * @var string
             *
             * @example full
             */
            'type' => ['sometimes', Rule::enum(BackupType::class)],

            /**
             * Storage disk name to write the backup to.
             *
             * @var string
             *
             * @example s3
             */
            'disk' => ['sometimes', 'string'],

            /**
             * Arbitrary backup metadata key-value pairs.
             *
             * @var array<string, mixed>
             *
             * @example {"triggered_by":"ops"}
             */
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
