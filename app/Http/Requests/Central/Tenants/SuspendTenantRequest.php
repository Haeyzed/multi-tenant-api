<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for suspending a central tenant.
 */
class SuspendTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('suspend', $tenant) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional explanation recorded with the suspension.
             * @var string|null
             * @example Non-payment after grace period expired.
             */
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
