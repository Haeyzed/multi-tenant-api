<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\LogLevel;
use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentStatus;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Refund;
use App\Models\Central\Subscription;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for processing payments and refunds against invoices.
 *
 * Delegates gateway interactions to configured drivers, records payment
 * attempts and audit logs, and updates invoice balances on successful charges.
 * Live checkout flows may leave a payment in processing until a webhook completes it.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager  $gateways,
        private readonly SubscriptionService    $subscriptions,
        private readonly PaymentGatewayResolver $gatewayResolver,
    )
    {
    }

    /**
     * Paginate payments with optional tenant, status, gateway, and search filters.
     *
     * @param array{tenant_id?: string, status?: string, gateway?: string, search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Payment::query()
            ->with(['invoice', 'tenant', 'attempts', 'refunds'])
            ->when($filters['tenant_id'] ?? null, fn($q, $id) => $q->where('tenant_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['gateway'] ?? null, fn($q, $gateway) => $q->where('gateway', $gateway))
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('gateway_reference', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('invoice', function ($invoiceQuery) use ($search): void {
                            $invoiceQuery->where('number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('tenant', function ($tenantQuery) use ($search): void {
                            $tenantQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                })
            )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     total: int,
     *     pending: int,
     *     processing: int,
     *     completed: int,
     *     failed: int,
     *     refunded: int,
     *     volume: float,
     *     by_status: array<string, int>,
     *     by_gateway: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = Payment::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        $byGateway = Payment::query()
            ->selectRaw('gateway, COUNT(*) as aggregate')
            ->groupBy('gateway')
            ->pluck('aggregate', 'gateway')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'pending' => (int)($byStatus[PaymentStatus::PENDING->value] ?? 0),
            'processing' => (int)($byStatus[PaymentStatus::PROCESSING->value] ?? 0),
            'completed' => (int)($byStatus[PaymentStatus::COMPLETED->value] ?? 0),
            'failed' => (int)($byStatus[PaymentStatus::FAILED->value] ?? 0),
            'refunded' => (int)($byStatus[PaymentStatus::REFUNDED->value] ?? 0),
            'volume' => (float)Payment::query()
                ->where('status', PaymentStatus::COMPLETED)
                ->sum('amount'),
            'by_status' => $byStatus,
            'by_gateway' => $byGateway,
        ];
    }

    /**
     * Charge an invoice through the configured payment gateway.
     *
     * Creates a payment record, records the gateway attempt, updates the
     * invoice balance on immediate success, or leaves the payment processing
     * when the provider returns a hosted checkout URL.
     *
     * @param Invoice $invoice
     * @param array{
     *     gateway?: string,
     *     amount?: float|null,
     *     force_failure?: bool,
     *     payment_method?: string,
     *     authorization_code?: string
     * } $options
     * @return Payment
     *
     * @throws ValidationException|Throwable
     */
    public function chargeInvoice(Invoice $invoice, array $options = []): Payment
    {
        return DB::transaction(function () use ($invoice, $options): Payment {
            if (in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::VOID], true)) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice cannot be charged.'],
                ]);
            }

            $amount = (float)($options['amount'] ?? $invoice->balanceDue());

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Charge amount must be greater than zero.'],
                ]);
            }

            $gatewayValue = $this->gatewayResolver->resolve(
                $invoice->currency,
                isset($options['gateway']) ? (string)$options['gateway'] : null,
            );
            $gateway = PaymentGateway::from($gatewayValue);
            $driver = $this->gateways->driver($gateway);

            $payment = Payment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription_id,
                'gateway' => $gateway,
                'status' => PaymentStatus::PROCESSING,
                'amount' => $amount,
                'currency' => $invoice->currency,
            ]);

            $attemptNumber = $payment->attempts()->count() + 1;

            $storedMethod = $options['payment_method_model'] ?? null;
            if ($storedMethod instanceof PaymentMethod) {
                $result = $driver->chargeOffSession($invoice, $payment, $storedMethod, $options);
            } else {
                $result = $driver->charge($invoice, $payment, $options);
            }

            $payment->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => $this->attemptStatusFromResult($result),
                'gateway_reference' => $result->reference,
                'response_message' => $result->message,
                'payload' => $result->raw,
            ]);

            $this->log(
                $payment,
                'charge_attempt',
                $result->successful ? LogLevel::INFO : LogLevel::ERROR,
                $result->message ?? 'Charge processed',
                $result->raw,
            );

            if (!$result->successful) {
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                    'failure_reason' => $result->message,
                    'gateway_reference' => $result->reference ?: null,
                ]);

                throw ValidationException::withMessages([
                    'payment' => [$result->message ?? 'Payment failed.'],
                ]);
            }

            if ($result->isPending()) {
                $payment->update([
                    'status' => PaymentStatus::PROCESSING,
                    'gateway_reference' => $result->reference,
                    'failure_reason' => null,
                ]);

                return $payment->fresh(['attempts', 'invoice', 'logs']);
            }

            return $this->markPaymentCompleted($payment, $invoice, $amount, $result->reference);
        });
    }

    private function attemptStatusFromResult(PaymentResult $result): PaymentStatus
    {
        if (!$result->successful) {
            return PaymentStatus::FAILED;
        }

        return $result->isPending() ? PaymentStatus::PROCESSING : PaymentStatus::COMPLETED;
    }

    /**
     * Persist a structured audit log entry for a payment event.
     *
     * @param Payment $payment
     * @param string $event
     * @param LogLevel $level
     * @param string|null $message
     * @param array<string, mixed> $context
     */
    private function log(Payment $payment, string $event, LogLevel $level, ?string $message, array $context = []): void
    {
        $payment->logs()->create([
            'tenant_id' => $payment->tenant_id,
            'gateway' => $payment->gateway,
            'event' => $event,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);
    }

    private function markPaymentCompleted(Payment $payment, Invoice $invoice, float $amount, string $reference): Payment
    {
        $payment->update([
            'status' => PaymentStatus::COMPLETED,
            'gateway_reference' => $reference,
            'paid_at' => now(),
            'failure_reason' => null,
        ]);

        $invoice->refresh();
        $paid = (float)$invoice->amount_paid + $amount;
        $fullyPaid = $paid >= (float)$invoice->total;
        $invoice->update([
            'amount_paid' => $paid,
            'status' => $fullyPaid ? InvoiceStatus::PAID : $invoice->status,
            'paid_at' => $fullyPaid ? now() : $invoice->paid_at,
        ]);

        if ($fullyPaid && $invoice->subscription_id) {
            $this->activateSubscriptionAfterInvoicePaid($invoice);
        }

        return $payment->fresh(['attempts', 'invoice', 'logs']);
    }

    /**
     * Activate trialing / past_due subscriptions once the conversion invoice is paid.
     */
    private function activateSubscriptionAfterInvoicePaid(Invoice $invoice): void
    {
        $subscription = Subscription::query()->find($invoice->subscription_id);

        if ($subscription === null) {
            return;
        }

        if (!in_array($subscription->status, [SubscriptionStatus::TRIALING, SubscriptionStatus::PAST_DUE], true)) {
            return;
        }

        $this->subscriptions->activateAfterPayment($subscription);

        $tenant = $subscription->tenant;
        if ($tenant !== null) {
            $tenant->update(['status' => TenantStatus::ACTIVE]);
        }
    }

    /**
     * Complete a previously initiated payment after provider confirmation.
     *
     * @param Payment $payment
     * @param string $reference
     * @param array<string, mixed> $raw
     * @return Payment
     *
     * @throws ValidationException|Throwable
     */
    public function completePayment(Payment $payment, string $reference, array $raw = []): Payment
    {
        return DB::transaction(function () use ($payment, $reference, $raw): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::COMPLETED) {
                return $payment->fresh(['attempts', 'invoice', 'logs']);
            }

            if (!in_array($payment->status, [PaymentStatus::PROCESSING, PaymentStatus::PENDING], true)) {
                throw ValidationException::withMessages([
                    'payment' => ['Only processing payments can be completed.'],
                ]);
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $this->log($payment, 'webhook_completed', LogLevel::INFO, 'Payment completed via provider webhook.', $raw);

            return $this->markPaymentCompleted($payment, $invoice, (float)$payment->amount, $reference);
        });
    }

    /**
     * Mark a payment failed after provider rejection.
     *
     * @param Payment $payment
     * @param string $message
     * @param array<string, mixed> $raw
     * @return Payment
     */
    public function failPayment(Payment $payment, string $message, array $raw = []): Payment
    {
        $payment->update([
            'status' => PaymentStatus::FAILED,
            'failure_reason' => $message,
        ]);

        $this->log($payment, 'webhook_failed', LogLevel::ERROR, $message, $raw);

        return $payment->fresh(['attempts', 'invoice', 'logs']);
    }

    /**
     * Refund a completed or partially refunded payment.
     *
     * Validates the refund amount, delegates to the original gateway driver,
     * and updates the payment status based on cumulative refunded totals.
     *
     * @param Payment $payment
     * @param array{amount?: float|null, reason?: string|null} $options
     * @return Refund
     *
     * @throws ValidationException|Throwable
     */
    public function refund(Payment $payment, array $options = []): Refund
    {
        return DB::transaction(function () use ($payment, $options): Refund {
            if ($payment->status !== PaymentStatus::COMPLETED && $payment->status !== PaymentStatus::PARTIALLY_REFUNDED) {
                throw ValidationException::withMessages([
                    'payment' => ['Only completed payments can be refunded.'],
                ]);
            }

            $amount = (float)($options['amount'] ?? ((float)$payment->amount - $payment->refundedAmount()));

            if ($amount <= 0 || $amount > ((float)$payment->amount - $payment->refundedAmount() + 0.00001)) {
                throw ValidationException::withMessages([
                    'amount' => ['Invalid refund amount.'],
                ]);
            }

            $driver = $this->gateways->driver($payment->gateway);
            $result = $driver->refund($payment, $amount, $options);

            $refund = Refund::query()->create([
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'status' => $result->successful ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
                'gateway_reference' => $result->reference ?: null,
                'reason' => $options['reason'] ?? null,
                'refunded_at' => $result->successful ? now() : null,
                'metadata' => $result->raw,
            ]);

            $this->log($payment, 'refund_attempt', $result->successful ? LogLevel::INFO : LogLevel::ERROR, $result->message ?? 'Refund processed', $result->raw);

            if (!$result->successful) {
                throw ValidationException::withMessages([
                    'refund' => [$result->message ?? 'Refund failed.'],
                ]);
            }

            $refunded = $payment->refundedAmount();
            $payment->update([
                'status' => $refunded >= (float)$payment->amount
                    ? PaymentStatus::REFUNDED
                    : PaymentStatus::PARTIALLY_REFUNDED,
            ]);

            return $refund->fresh('payment');
        });
    }
}
