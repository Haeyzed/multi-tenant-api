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
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(BackupType::class)],
            'disk' => ['sometimes', 'string'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
