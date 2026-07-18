<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for soft-deleting multiple central users.
 */
class BulkDeleteUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * User IDs to soft-delete.
             *
             * @var list<int>
             *
             * @example [2, 3]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],

            /**
             * A single user ID.
             *
             * @var int
             *
             * @example 2
             */
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::notIn([(int) $this->user()?->id]),
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.*.not_in' => 'You cannot delete your own account.',
        ];
    }
}
