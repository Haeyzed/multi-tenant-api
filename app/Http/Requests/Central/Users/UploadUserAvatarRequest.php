<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates avatar image upload for a central user.
 */
class UploadUserAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Avatar image file (max 2 MB).
             * @var \Illuminate\Http\UploadedFile
             * @example avatar.jpg
             */
            'avatar' => ['required', 'image', 'max:2048'],
        ];
    }
}
