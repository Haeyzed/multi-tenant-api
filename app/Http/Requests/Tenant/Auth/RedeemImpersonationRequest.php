<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates redemption of a central impersonation token on a tenant.
 */
class RedeemImpersonationRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
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
             * One-time impersonation token issued by the central application.
             * @var string
             * @example eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
             */
            'token' => ['required', 'string'],
        ];
    }
}
