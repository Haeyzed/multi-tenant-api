<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Payments\Contracts\PaymentGatewayDriver;
use App\Payments\DTOs\WebhookResult;
use App\Payments\PaymentMethodPayload;
use App\Payments\PaymentResult;
use App\Payments\SetupSessionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Base gateway used for offline/manual settlement and shared helpers.
 */
abstract class AbstractGatewayDriver implements PaymentGatewayDriver
{
    public function supportsRecurring(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult
    {
        if (($options['force_failure'] ?? false) === true) {
            return PaymentResult::failure($this->name().' charge declined.', $this->reference('fail'), [
                'gateway' => $this->name(),
                'invoice_id' => $invoice->id,
            ]);
        }

        return PaymentResult::success($this->reference('ch'), 'completed', [
            'gateway' => $this->name(),
            'invoice_id' => $invoice->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);
    }

    protected function reference(string $prefix): string
    {
        return Str::upper($this->name()).'_'.$prefix.'_'.Str::upper(Str::random(16));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult
    {
        if (! $this->supportsRefunds()) {
            return PaymentResult::failure($this->name().' does not support refunds.');
        }

        return PaymentResult::success($this->reference('re'), 'refunded', [
            'gateway' => $this->name(),
            'payment_id' => $payment->id,
            'amount' => $amount,
        ]);
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createSetupSession(array $options): SetupSessionResult
    {
        return SetupSessionResult::failure(
            "{$this->name()} does not support card verification at signup.",
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function confirmSetup(string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult
    {
        return SetupSessionResult::failure(
            "{$this->name()} does not support card verification at signup.",
            $reference,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeOffSession(Invoice $invoice, Payment $payment, PaymentMethod $method, array $options = []): PaymentResult
    {
        return PaymentResult::failure(
            "{$this->name()} does not support off-session charges.",
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createCustomer(array $options = []): array
    {
        return ['id' => $this->reference('customer'), 'gateway' => $this->name()];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createSubscription(array $options = []): array
    {
        return ['id' => $this->reference('subscription'), 'gateway' => $this->name()];
    }

    public function cancelSubscription(string $subscriptionId): void {}

    public function resumeSubscription(string $subscriptionId): void {}

    /**
     * Ignore webhooks for gateways without a provider webhook implementation.
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        return WebhookResult::ignored();
    }

    /**
     * Report that payment verification is not available for this gateway.
     */
    public function verifyPayment(string $reference): PaymentResult
    {
        return PaymentResult::failure("{$this->name()} does not support payment verification.", $reference);
    }
}
