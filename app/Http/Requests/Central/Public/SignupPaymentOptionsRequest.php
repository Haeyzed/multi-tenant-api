<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates country for public signup payment gateway options.
 */
class SignupPaymentOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            /**
             * ISO 3166-1 alpha-2 country code used to resolve available gateways.
             *
             * @var string
             *
             * @example NG
             */
            'country' => ['required', 'string', 'size:2'],
        ];
    }
}
