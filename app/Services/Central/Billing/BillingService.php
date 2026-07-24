<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Models\Central\BillingProfile;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Refund;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\User;

/**
 * Thin billing facade over subscription, payment, invoice, and profile services.
 */
final class BillingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
        private readonly PaymentGatewayResolver $gateways,
        private readonly PlanPriceResolver $prices,
        private readonly BillingProfileService $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubscription(array $data, ?User $actor = null): Subscription
    {
        return $this->subscriptions->create($data, $actor);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeInvoice(Invoice $invoice, array $options = []): Payment
    {
        return $this->payments->chargeInvoice($invoice, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function refund(Payment $payment, array $options = []): Refund
    {
        return $this->payments->refund($payment, $options);
    }

    public function resolveGateway(
        ?string $currency = null,
        ?string $explicitGateway = null,
        ?string $countryIso2 = null,
        ?string $preferredGateway = null,
    ): string {
        return $this->gateways->resolve($currency, $explicitGateway, $countryIso2, $preferredGateway);
    }

    public function resolvePlanPrice(
        Plan $plan,
        ?string $countryIso2 = null,
        ?string $currency = null,
        mixed $interval = null,
        ?BillingProfile $billingProfile = null,
    ): PlanPrice {
        return $this->prices->resolve($plan, $countryIso2, $currency, $interval, $billingProfile);
    }

    /**
     * @param  array{country_iso2?: string|null, currency?: string|null, preferred_gateway?: string|null, metadata?: array<string, mixed>|null}  $attributes
     */
    public function updateBillingProfile(Tenant $tenant, array $attributes): BillingProfile
    {
        return $this->profiles->update($tenant, $attributes);
    }

    public function invoices(): InvoiceService
    {
        return $this->invoices;
    }
}
