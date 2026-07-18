<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for activating multiple central users.
 */
class BulkActivateUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage-status') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * User IDs to activate.
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
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
