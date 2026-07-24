<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates gateway selection for a signed public invoice payment.
 */
class PayPublicInvoiceRequest extends FormRequest
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
            'gateway' => ['required', 'string', 'max:50'],
        ];
    }
}
