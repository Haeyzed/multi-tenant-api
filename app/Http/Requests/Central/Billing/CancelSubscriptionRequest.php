<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for cancelling a subscription.
 */
class CancelSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            /**
             * Cancel immediately instead of at period end.
             *
             * @var bool
             *
             * @example false
             */
            'immediately' => ['sometimes', 'boolean'],

            /**
             * Optional cancellation reason.
             *
             * @var string|null
             *
             * @example Switching to a different plan
             */
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
