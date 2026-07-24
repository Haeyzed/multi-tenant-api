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
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
