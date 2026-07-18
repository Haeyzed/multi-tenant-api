<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a full tag sync payload for a central tenant.
 */
class SyncTenantTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('manageTags', $tenant) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Complete list of tags to assign to the tenant.
             * @var list<string>
             * @example ["saas","retail","enterprise"]
             */
            'tags' => ['required', 'array'],

            /**
             * Single tag value.
             * @var string
             * @example retail
             */
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
