<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Models\Central\BillingAddress;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\SubscriptionService;
use App\Services\Central\World\WorldService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestrates public self-serve tenant signup on a trial plan.
 *
 * Creates the tenant with an active owner password, then attaches a TRIALING
 * subscription without charging. Price and gateway are resolved from billing
 * country → currency → PlanPrice → gateway settings.
 *
 * Tenant creation must NOT run inside a DB transaction: TenantCreated runs
 * CreateDatabase (DDL), which implicitly commits on MySQL.
 */
final class TenantSignupService
{
    public function __construct(
        private readonly TenantService       $tenantService,
        private readonly SubscriptionService $subscriptionService,
        private readonly WorldService        $world,
    )
    {
    }

    /**
     * Sign up a new tenant on a publicly visible plan with a trial subscription.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     plan_id: int,
     *     country: string,
     *     slug?: string,
     *     phone?: string|null,
     *     domain?: string|null,
     *     owner_name?: string|null,
     *     billing_interval?: string|null,
     *     billing_address?: array{
     *         name?: string|null,
     *         company?: string|null,
     *         line1?: string|null,
     *         line2?: string|null,
     *         city?: string|null,
     *         state?: string|null,
     *         postal_code?: string|null,
     *         tax_id?: string|null,
     *         tax_type?: string|null
     *     }|null
     * } $data
     * @return array{tenant: Tenant, subscription: Subscription}
     *
     * @throws ValidationException|Throwable
     */
    public function signup(array $data): array
    {
        $plan = Plan::query()->findOrFail($data['plan_id']);

        if (!$plan->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not available for self-serve signup.'],
            ]);
        }

        $country = Str::upper(trim((string)$data['country']));
        $worldCountry = $this->world->findCountryByIso2($country);

        if ($worldCountry === null) {
            throw ValidationException::withMessages([
                'country' => ['The selected country is invalid.'],
            ]);
        }

        $tenant = $this->tenantService->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'domain' => $data['domain'] ?? null,
            'trial_ends_at' => null,
            'owner_password' => $data['password'],
            'owner_name' => $data['owner_name'] ?? null,
            'metadata' => [
                'signup_source' => 'self_serve',
                'billing_country' => $country,
            ],
        ]);

        return DB::transaction(function () use ($data, $plan, $country, $tenant): array {
            $addressPayload = is_array($data['billing_address'] ?? null) ? $data['billing_address'] : [];
            $billingAddress = BillingAddress::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $addressPayload['name'] ?? ($data['owner_name'] ?? $data['name']),
                'company' => $addressPayload['company'] ?? $data['name'],
                'line1' => $addressPayload['line1'] ?? 'Address pending',
                'line2' => $addressPayload['line2'] ?? null,
                'city' => $addressPayload['city'] ?? 'Pending',
                'state' => $addressPayload['state'] ?? null,
                'postal_code' => $addressPayload['postal_code'] ?? '00000',
                'country' => $country,
                'tax_id' => $addressPayload['tax_id'] ?? null,
                'tax_type' => $addressPayload['tax_type'] ?? null,
                'is_default' => true,
            ]);

            $subscription = $this->subscriptionService->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'country' => $country,
                'billing_interval' => $data['billing_interval'] ?? null,
                'billing_address_id' => $billingAddress->id,
            ]);

            if ($subscription->trial_ends_at !== null) {
                $tenant->update(['trial_ends_at' => $subscription->trial_ends_at]);
            }

            return [
                'tenant' => $tenant->fresh(['domains'])->loadCount(['domains', 'notes']),
                'subscription' => $subscription->load(['plan', 'planPrice', 'invoices']),
            ];
        });
    }
}
