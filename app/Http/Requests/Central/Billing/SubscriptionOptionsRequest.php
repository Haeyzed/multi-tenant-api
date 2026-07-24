<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Subscription;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query filters for subscription combobox options.
 */
class SubscriptionOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Subscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Optional tenant UUID to scope subscription options.
             *
             * @var string|null
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],

            /**
             * Optional search term for filtering subscriptions.
             *
             * @var string|null
             *
             * @example Pro Plan
             */
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
