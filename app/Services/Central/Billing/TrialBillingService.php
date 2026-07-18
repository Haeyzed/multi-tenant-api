<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\PaymentStatus;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Mail\Tenant\TrialEndedMail;
use App\Mail\Tenant\TrialEndingMail;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Processes trial reminders, trial endings, and public signed checkout conversion.
 */
final class TrialBillingService
{
    public function __construct(
        private readonly InvoiceService      $invoiceService,
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentService      $paymentService,
        private readonly BillingSettings     $billingSettings,
    )
    {
    }

    /**
     * Send trial-ending reminders and convert ended trials to past_due + invoice.
     *
     * @return array{reminders: int, ended: int}
     */
    public function processTrials(): array
    {
        return [
            'reminders' => $this->sendTrialEndingReminders(),
            'ended' => $this->processEndedTrials(),
        ];
    }

    /**
     * @return int Number of reminder emails sent
     */
    private function sendTrialEndingReminders(): int
    {
        $days = $this->billingSettings->trialReminderDays();
        $sent = 0;

        $this->subscriptionsNeedingReminder($days)->each(function (Subscription $subscription) use (&$sent): void {
            $tenant = $subscription->tenant;

            if (!filled($tenant?->email)) {
                return;
            }

            $daysLeft = max(0, (int)now()->diffInDays($subscription->trial_ends_at, false));
            $checkoutUrl = $this->signedCheckoutUrl($subscription);

            Mail::to($tenant->email)->send(new TrialEndingMail(
                tenantName: (string)$tenant->name,
                planName: (string)($subscription->plan?->name ?? 'your plan'),
                daysLeft: $daysLeft,
                trialEndsAt: $subscription->trial_ends_at,
                checkoutUrl: $checkoutUrl,
            ));

            $metadata = $subscription->metadata ?? [];
            $metadata['trial_reminder_sent_at'] = now()->toIso8601String();
            $subscription->update(['metadata' => $metadata]);
            $sent++;
        });

        return $sent;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function subscriptionsNeedingReminder(int $days): Collection
    {
        $from = now()->startOfDay();
        $to = now()->addDays($days)->endOfDay();

        return Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', SubscriptionStatus::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$from, $to])
            ->where(function ($query): void {
                $query->whereNull('metadata->trial_reminder_sent_at')
                    ->orWhere('metadata->trial_reminder_sent_at', '');
            })
            ->get();
    }

    /**
     * Build a temporary signed checkout URL for a subscription.
     *
     * Emails link to the frontend checkout page; the page forwards expires/signature
     * to the signed API route to start gateway checkout.
     */
    public function signedCheckoutUrl(Subscription $subscription): string
    {
        $apiUrl = $this->signedApiCheckoutUrl($subscription);
        $query = parse_url($apiUrl, PHP_URL_QUERY) ?: '';

        $frontendBase = rtrim((string)config('billing.frontend_url'), '/');
        $path = str_replace(
            '{subscription}',
            (string)$subscription->id,
            (string)config('billing.frontend_checkout_path', '/central/billing/checkout/{subscription}'),
        );

        return $frontendBase . $path . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Build the signed API checkout URL (used by the frontend and direct API clients).
     */
    public function signedApiCheckoutUrl(Subscription $subscription): string
    {
        $hours = $this->billingSettings->checkoutLinkTtlHours();

        // Prefer APP_URL host (e.g. multi-tenant-api.test) over 127.0.0.1 domain routes.
        URL::forceRootUrl(rtrim((string)config('app.url'), '/'));

        return URL::temporarySignedRoute(
            'central.public.billing.checkout',
            now()->addHours($hours),
            ['subscription' => $subscription->id],
        );
    }

    /**
     * @return int Number of trials converted
     */
    private function processEndedTrials(): int
    {
        $processed = 0;
        $graceDays = $this->billingSettings->pastDueGraceDays();

        $this->subscriptionsWithEndedTrials()->each(function (Subscription $subscription) use (&$processed, $graceDays): void {
            $this->ensureOpenInvoice($subscription);
            $this->subscriptionService->markPastDue($subscription, $graceDays);

            $tenant = $subscription->tenant;
            if ($tenant !== null) {
                $tenant->update(['status' => TenantStatus::GRACE_PERIOD]);
            }

            if (filled($tenant?->email)) {
                Mail::to($tenant->email)->send(new TrialEndedMail(
                    tenantName: (string)$tenant->name,
                    planName: (string)($subscription->plan?->name ?? 'your plan'),
                    checkoutUrl: $this->signedCheckoutUrl($subscription->fresh()),
                ));
            }

            $metadata = $subscription->fresh()->metadata ?? [];
            $metadata['trial_ended_processed_at'] = now()->toIso8601String();
            $subscription->update(['metadata' => $metadata]);
            $processed++;
        });

        return $processed;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function subscriptionsWithEndedTrials(): Collection
    {
        return Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', SubscriptionStatus::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('metadata->trial_ended_processed_at')
                    ->orWhere('metadata->trial_ended_processed_at', '');
            })
            ->get();
    }

    private function ensureOpenInvoice(Subscription $subscription): Invoice
    {
        $existing = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', [InvoiceStatus::OPEN, InvoiceStatus::PENDING, InvoiceStatus::OVERDUE])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->invoiceService->createForSubscription($subscription, [
            'description' => ($subscription->plan?->name ?? 'Subscription') . ' — trial conversion',
            'idempotency_key' => "subscription:{$subscription->id}:trial-conversion",
        ]);
    }

    /**
     * Respond to a signed checkout request as redirect or JSON.
     * @throws Throwable
     */
    public function checkoutResponse(Subscription $subscription, bool $asJson = false): RedirectResponse|JsonResponse
    {
        $result = $this->startCheckout($subscription);

        if ($asJson) {
            return response()->json([
                'status' => true,
                'message' => $result['completed']
                    ? 'Payment completed successfully.'
                    : 'Checkout session created successfully.',
                'data' => [
                    'checkout_url' => $result['checkout_url'],
                    'completed' => $result['completed'],
                    'payment_id' => $result['payment']->id,
                    'invoice_id' => $result['invoice']->id,
                    'subscription_id' => $subscription->id,
                ],
                'meta' => (object)[],
                'errors' => null,
            ]);
        }

        if (filled($result['checkout_url'])) {
            return redirect()->away($result['checkout_url']);
        }

        if ($result['completed']) {
            $success = (string)config('payments.success_url', config('app.url'));

            return redirect()->away(str_replace(
                ['{payment}', '{invoice}', '{tenant}'],
                [
                    (string)$result['payment']->id,
                    (string)$result['invoice']->id,
                    (string)$subscription->tenant_id,
                ],
                $success,
            ));
        }

        throw ValidationException::withMessages([
            'payment' => ['Checkout URL was not returned by the payment gateway.'],
        ]);
    }

    /**
     * Start or refresh checkout for a trialing / past_due subscription.
     *
     * @return array{payment: Payment, invoice: Invoice, checkout_url: string|null, completed: bool}
     *
     * @throws ValidationException|Throwable
     */
    public function startCheckout(Subscription $subscription): array
    {
        if (!in_array($subscription->status, [SubscriptionStatus::TRIALING, SubscriptionStatus::PAST_DUE], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['Only trialing or past due subscriptions can start checkout.'],
            ]);
        }

        $subscription->loadMissing(['tenant', 'plan', 'defaultPaymentMethod']);

        $invoice = $this->ensureOpenInvoice($subscription);

        $options = array_filter([
            'gateway' => $subscription->gateway?->value,
        ]);

        if ($subscription->defaultPaymentMethod !== null) {
            $options['payment_method_model'] = $subscription->defaultPaymentMethod;
        }

        $payment = $this->paymentService->chargeInvoice($invoice, $options);

        $payment->loadMissing('attempts');

        $checkoutUrl = $payment->attempts->last()?->payload['checkout_url']
            ?? $payment->attempts->last()?->payload['authorization_url']
            ?? null;

        return [
            'payment' => $payment,
            'invoice' => $invoice->fresh(),
            'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
            'completed' => $payment->status === PaymentStatus::COMPLETED,
        ];
    }
}
