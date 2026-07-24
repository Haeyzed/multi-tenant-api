<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload when marking a subscription past due.
 */
class MarkSubscriptionPastDueRequest extends FormRequest
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
             * Number of grace days before further enforcement.
             *
             * @var int
             *
             * @example 7
             */
            'grace_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ];
    }
}
