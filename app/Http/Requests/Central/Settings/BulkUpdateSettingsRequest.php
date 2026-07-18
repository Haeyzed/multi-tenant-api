<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for bulk-updating multiple platform setting values.
 */
class BulkUpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Map of setting keys to new values.
             * @var array<string, mixed>
             * @example {"app.support_email":"support@example.com","maintenance.enabled":false}
             */
            'settings' => ['required', 'array', 'min:1'],

            /**
             * Value for a single setting key.
             * @var mixed
             * @example support@example.com
             */
            'settings.*' => ['nullable'],
        ];
    }
}
