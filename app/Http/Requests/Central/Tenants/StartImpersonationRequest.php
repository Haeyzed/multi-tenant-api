<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Tenants;

use App\Enums\Central\ImpersonationReason;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for starting a tenant impersonation session.
 */
class StartImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('impersonate', $tenant) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Reason category for the impersonation session.
             * @var string
             * @example support
             */
            'reason' => ['required', Rule::enum(ImpersonationReason::class)],

            /**
             * Optional free-text notes explaining the impersonation.
             * @var string|null
             * @example Investigating billing discrepancy reported by tenant admin.
             */
            'reason_notes' => ['sometimes', 'nullable', 'string', 'max:500'],

            /**
             * Session time-to-live in minutes.
             * @var int
             * @example 60
             */
            'ttl_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
        ];
    }
}
