<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for cancelling a subscription.
 */
class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $this->user()?->can('manage', $subscription) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'immediately' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
