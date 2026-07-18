<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a completed public signup.
 *
 * @property-read Tenant $tenant
 * @property-read Subscription $subscription
 */
class PublicSignupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource['tenant'];
        /** @var Subscription $subscription */
        $subscription = $this->resource['subscription'];

        $primaryDomain = $tenant->domains->firstWhere('is_primary', true)?->domain
            ?? $tenant->domains->first()?->domain;

        return [
            /**
             * Newly created tenant.
             *
             * @var TenantResource
             */
            'tenant' => new TenantResource($tenant),

            /**
             * Trialing subscription for the selected plan.
             *
             * @var SubscriptionResource
             */
            'subscription' => new SubscriptionResource($subscription),

            /**
             * Where the owner should authenticate next.
             *
             * @var array{primary_domain: string|null, auth_base_path: string, message: string}
             */
            'login' => [
                /**
                 * Primary tenant hostname for owner login.
                 *
                 * @var string|null
                 *
                 * @example acme.localhost
                 */
                'primary_domain' => $primaryDomain,

                /**
                 * Tenant API auth base path.
                 *
                 * @var string
                 *
                 * @example /api/v1/auth
                 */
                'auth_base_path' => '/api/v1/auth',

                /**
                 * Human-readable next step for the client.
                 *
                 * @var string
                 */
                'message' => 'Log in on the tenant domain with your email and password to continue.',
            ],
        ];
    }
}
