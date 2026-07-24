<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates country for public signup payment gateway options.
 */
class SignupPaymentOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'size:2'],
        ];
    }
}
