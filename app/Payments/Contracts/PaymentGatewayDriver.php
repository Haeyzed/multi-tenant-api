<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Payments\DTOs\WebhookResult;
use App\Payments\PaymentMethodPayload;
use App\Payments\PaymentResult;
use App\Payments\SetupSessionResult;
use Illuminate\Http\Request;

/**
 * Contract implemented by every payment gateway driver.
 */
interface PaymentGatewayDriver
{
    /**
     * Stable gateway identifier (matches PaymentGateway enum values).
     */
    public function name(): string;

    /**
     * Charge an invoice through this gateway for the given payment record.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult;

    /**
     * Refund (part of) a previously captured payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult;

    /**
     * Whether this gateway can process refunds.
     */
    public function supportsRefunds(): bool;

    /**
     * Whether this gateway can process recurring / subscription charges.
     */
    public function supportsRecurring(): bool;

    /**
     * Start a hosted card setup / soft-verification session.
     *
     * @param  array{
     *     email: string,
     *     currency: string,
     *     amount: float,
     *     success_url: string,
     *     cancel_url: string,
     *     reference?: string,
     *     metadata?: array<string, mixed>
     * }  $options
     */
    public function createSetupSession(array $options): SetupSessionResult;

    /**
     * Confirm a setup session and return a normalized payment method payload.
     *
     * @param  array<string, mixed>  $options
     */
    public function confirmSetup(string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult;

    /**
     * Charge an invoice using a stored payment method (off-session).
     *
     * @param  array<string, mixed>  $options
     */
    public function chargeOffSession(Invoice $invoice, Payment $payment, PaymentMethod $method, array $options = []): PaymentResult;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createCustomer(array $options = []): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createSubscription(array $options = []): array;

    public function cancelSubscription(string $subscriptionId): void;

    public function resumeSubscription(string $subscriptionId): void;

    /**
     * Verify and normalize an inbound provider webhook without mutating payments.
     */
    public function handleWebhook(Request $request): WebhookResult;

    /**
     * Verify the provider-side status for a payment reference.
     */
    public function verifyPayment(string $reference): PaymentResult;
}
