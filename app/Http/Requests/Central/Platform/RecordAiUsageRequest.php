<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for recording AI provider token usage.
 */
class RecordAiUsageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('ai.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Number of tokens consumed by the request.
             *
             * @var int
             *
             * @example 1250
             */
            'tokens' => ['required', 'integer', 'min:1'],

            /**
             * Optional credit cost deducted for this usage.
             *
             * @var float
             *
             * @example 0.05
             */
            'credit_cost' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
