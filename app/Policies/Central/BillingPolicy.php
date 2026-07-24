<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

/**
 * Authorization checks for central billing invoices, payments, and gateways.
 *
 * Registered as named Gate abilities (not a model policy) because billing
 * spans multiple Eloquent models.
 */
final class BillingPolicy
{
    public function viewInvoices(User $user): bool
    {
        return $user->can('billing.invoices.view');
    }

    public function manageInvoices(User $user): bool
    {
        return $user->can('billing.invoices.manage');
    }

    public function viewPayments(User $user): bool
    {
        return $user->can('billing.payments.view');
    }

    public function charge(User $user): bool
    {
        return $user->can('billing.payments.charge');
    }

    public function refund(User $user): bool
    {
        return $user->can('billing.payments.refund');
    }

    public function manageAddresses(User $user): bool
    {
        return $user->can('billing.addresses.manage');
    }

    public function viewGateways(User $user): bool
    {
        return $user->can('billing.gateways.view');
    }

    /**
     * Determine whether the user may view a tenant billing profile.
     */
    public function viewBillingProfile(User $user): bool
    {
        return $user->can('tenants.view') || $user->can('billing.invoices.view');
    }

    /**
     * Determine whether the user may list gateway dropdown options.
     */
    public function viewGatewayOptions(User $user): bool
    {
        return $user->can('billing.gateways.view') || $user->can('billing.payments.charge');
    }
}
