<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Settings;

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Models\Central\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a platform setting definition.
 */
class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Setting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Setting group/category.
             * @var string
             * @example platform
             */
            'group' => ['required', Rule::enum(SettingGroup::class)],

            /**
             * Unique setting key within the platform.
             * @var string
             * @example app.support_email
             */
            'key' => ['required', 'string', 'max:255', 'unique:settings,key'],

            /**
             * Human-readable setting label.
             * @var string
             * @example Support Email
             */
            'label' => ['required', 'string', 'max:255'],

            /**
             * Optional admin-facing description.
             * @var string|null
             * @example Email address shown on support pages.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Value storage and validation type.
             * @var string
             * @example string
             */
            'type' => ['required', Rule::enum(SettingType::class)],

            /**
             * Current setting value.
             * @var mixed
             * @example support@example.com
             */
            'value' => ['sometimes', 'nullable'],

            /**
             * Default value when none is stored.
             * @var mixed
             * @example support@example.com
             */
            'default_value' => ['sometimes', 'nullable'],

            /**
             * Select/multi-select option definitions.
             * @var array<string, mixed>|null
             * @example {"choices":[{"label":"Enabled","value":true}]}
             */
            'options' => ['sometimes', 'nullable', 'array'],

            /**
             * Whether the setting is exposed to tenant-facing APIs.
             * @var bool
             * @example false
             */
            'is_public' => ['sometimes', 'boolean'],

            /**
             * Whether the stored value should be encrypted at rest.
             * @var bool
             * @example false
             */
            'is_encrypted' => ['sometimes', 'boolean'],

            /**
             * Whether the value can only be changed programmatically.
             * @var bool
             * @example false
             */
            'is_readonly' => ['sometimes', 'boolean'],

            /**
             * Display order within the settings UI.
             * @var int
             * @example 10
             */
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
