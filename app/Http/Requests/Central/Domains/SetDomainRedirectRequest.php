<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Domains;

use App\Models\Central\Domain;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payload for configuring domain redirect behavior.
 */
class SetDomainRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return $this->user()?->can('update', $domain) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Target hostname to redirect to, or null to clear redirect.
             * @var string|null
             * @example www.freshbasket.com
             */
            'redirect_to' => ['nullable', 'string', 'max:255'],
        ];
    }
}
