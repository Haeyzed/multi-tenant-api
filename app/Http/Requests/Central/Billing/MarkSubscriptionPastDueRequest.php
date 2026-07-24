<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload when marking a subscription past due.
 */
class MarkSubscriptionPastDueRequest extends FormRequest
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
            'grace_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ];
    }
}
