<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Plans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for soft-deleting multiple subscription plans.
 */
class BulkDeletePlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plans.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Plan IDs to soft-delete.
             *
             * @var list<int>
             *
             * @example [1, 2, 3]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],

            /**
             * A single plan primary key.
             *
             * @var int
             *
             * @example 1
             */
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('plans', 'id')->whereNull('deleted_at')],
        ];
    }
}
