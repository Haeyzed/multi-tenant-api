<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for recording metered feature usage for a tenant.
 */
class RecordFeatureUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plans.record-usage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant identifier consuming the feature.
             * @var string
             * @example tenant_01HXYZABCDEF
             */
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],

            /**
             * Feature identifier being metered.
             * @var int
             * @example 12
             */
            'feature_id' => ['required', 'integer', 'exists:features,id'],

            /**
             * Optional plan context for the usage event.
             * @var int|null
             * @example 4
             */
            'plan_id' => ['sometimes', 'nullable', 'integer', 'exists:plans,id'],

            /**
             * Usage amount to record.
             * @var int
             * @example 1
             */
            'amount' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
