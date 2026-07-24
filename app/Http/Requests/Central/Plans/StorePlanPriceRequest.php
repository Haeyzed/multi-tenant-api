<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for creating or upserting a plan price.
 */
class StorePlanPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $this->user()?->can('update', $plan) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['sometimes', 'string', 'in:monthly,quarterly,yearly'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,archived'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'gateway_identifiers' => ['sometimes', 'nullable', 'array'],
            'gateway_identifiers.stripe' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gateway_identifiers.paystack' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gateway_identifiers.flutterwave' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
