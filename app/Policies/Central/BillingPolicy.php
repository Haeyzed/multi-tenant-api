<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Models\User;

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
}
