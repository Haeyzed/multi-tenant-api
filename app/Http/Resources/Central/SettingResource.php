<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a platform setting.
 *
 * @mixin Setting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isEncrypted = (bool) $this->is_encrypted;

        return [
            /**
             * Setting primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Setting group value.
             *
             * @var string|null
             *
             * @example billing
             */
            'group' => $this->group?->value,

            /**
             * Human-readable setting group label.
             *
             * @var string|null
             */
            'group_label' => $this->group?->label(),

            /**
             * Unique setting key within the group.
             *
             * @var string
             *
             * @example stripe.publishable_key
             */
            'key' => $this->key,

            /**
             * Human-readable setting label.
             *
             * @var string|null
             */
            'label' => $this->label,

            /**
             * Setting description for administrators.
             *
             * @var string|null
             */
            'description' => $this->description,

            /**
             * Value type identifier.
             *
             * @var string|null
             *
             * @example string
             */
            'type' => $this->type?->value,

            /**
             * Human-readable value type label.
             *
             * @var string|null
             */
            'type_label' => $this->type?->label(),

            /**
             * Resolved setting value (null when encrypted).
             *
             * @var mixed
             */
            'value' => $isEncrypted ? null : $this->resolvedValue(),

            /**
             * Whether a non-null value is stored.
             *
             * @var bool
             */
            'has_value' => $this->value !== null,

            /**
             * Whether the value is masked because it is encrypted.
             *
             * @var bool
             */
            'is_masked' => $isEncrypted,

            /**
             * Configured default value.
             *
             * @var mixed
             */
            'default_value' => $this->default_value['value'] ?? null,

            /**
             * Selectable options for enum-like settings.
             *
             * @var array<string, mixed>|null
             */
            'options' => $this->options,

            /**
             * Whether the setting is exposed to public clients.
             *
             * @var bool
             */
            'is_public' => $this->is_public,

            /**
             * Whether the stored value is encrypted at rest.
             *
             * @var bool
             */
            'is_encrypted' => $isEncrypted,

            /**
             * Whether the setting cannot be modified via API.
             *
             * @var bool
             */
            'is_readonly' => $this->is_readonly,

            /**
             * Display sort order within the group.
             *
             * @var int
             */
            'sort_order' => $this->sort_order,

            /**
             * Creation timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             *
             * @example 2026-07-13T11:22:26.000000Z
             */
            'created_at' => $this->created_at,

            /**
             * Last update timestamp (ISO-8601).
             *
             * @var string|null
             *
             * @format date-time
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
