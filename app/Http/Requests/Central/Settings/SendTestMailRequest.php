<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the recipient email for a settings mail test.
 */
class SendTestMailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Recipient address for the test email.
             *
             * @var string
             *
             * @example ops@example.test
             */
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
