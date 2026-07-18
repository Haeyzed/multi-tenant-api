<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Payments\PaymentMethodPayload;
use App\Payments\PaymentResult;
use App\Payments\SetupSessionResult;
use App\Payments\Support\InteractsWithPaymentHttp;
use Throwable;

/**
 * Live Stripe Checkout / PaymentIntent driver (test or live credentials).
 */
final class StripeDriver extends AbstractGatewayDriver
{
    use InteractsWithPaymentHttp;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Create a Checkout Session or confirm a PaymentIntent.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult
    {
        if (($options['force_failure'] ?? false) === true) {
            return PaymentResult::failure('Stripe charge declined.', '', ['forced' => true]);
        }

        $secret = (string) config('payments.stripe.secret');

        if ($secret === '') {
            return PaymentResult::failure('Stripe is not configured. Set STRIPE_SECRET.');
        }

        try {
            if (filled($options['payment_method'] ?? null)) {
                return $this->confirmPaymentIntent($invoice, $payment, $options, $secret);
            }

            return $this->createCheckoutSession($invoice, $payment, $secret);
        } catch (Throwable $e) {
            return PaymentResult::failure($e->getMessage(), '', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Refund via Stripe Refunds API.
     *
     * @param  array<string, mixed>  $options
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult
    {
        $secret = (string) config('payments.stripe.secret');

        if ($secret === '' || blank($payment->gateway_reference)) {
            return PaymentResult::failure('Stripe refund requires STRIPE_SECRET and a gateway reference.');
        }

        $response = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->asForm()
            ->post('/v1/refunds', [
                'payment_intent' => $payment->gateway_reference,
                'amount' => $this->toMinorUnits($amount, (string) $payment->currency),
                'reason' => $options['reason'] ?? 'requested_by_customer',
            ]);

        if ($response->failed()) {
            return PaymentResult::failure(
                $response->json('error.message') ?? 'Stripe refund failed.',
                '',
                $response->json() ?? [],
            );
        }

        return PaymentResult::success(
            (string) $response->json('id'),
            'refunded',
            $response->json() ?? [],
        );
    }

    /**
     * Confirm an immediate PaymentIntent when a payment method is supplied.
     *
     * @param  array<string, mixed>  $options
     */
    private function confirmPaymentIntent(Invoice $invoice, Payment $payment, array $options, string $secret): PaymentResult
    {
        $response = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->asForm()
            ->post('/v1/payment_intents', [
                'amount' => $this->toMinorUnits((float) $payment->amount, (string) $payment->currency),
                'currency' => strtolower((string) $payment->currency),
                'payment_method' => $options['payment_method'],
                'confirm' => 'true',
                'automatic_payment_methods[enabled]' => 'true',
                'automatic_payment_methods[allow_redirects]' => 'never',
                'receipt_email' => $this->customerEmail($invoice),
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[invoice_id]' => (string) $invoice->id,
                'metadata[tenant_id]' => (string) $payment->tenant_id,
            ]);

        if ($response->failed()) {
            return PaymentResult::failure(
                $response->json('error.message') ?? 'Stripe PaymentIntent failed.',
                (string) ($response->json('error.payment_intent') ?? ''),
                $response->json() ?? [],
            );
        }

        $status = (string) $response->json('status');
        $id = (string) $response->json('id');

        if ($status === 'succeeded') {
            return PaymentResult::success($id, 'completed', $response->json() ?? []);
        }

        return PaymentResult::pending($id, $response->json() ?? [], 'Stripe payment requires further action.');
    }

    /**
     * Create a hosted Checkout Session and return a pending result with checkout_url.
     */
    private function createCheckoutSession(Invoice $invoice, Payment $payment, string $secret): PaymentResult
    {
        $response = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->asForm()
            ->post('/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $this->redirectUrl('success_url', $payment),
                'cancel_url' => $this->redirectUrl('cancel_url', $payment),
                'customer_email' => $this->customerEmail($invoice),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower((string) $payment->currency),
                'line_items[0][price_data][unit_amount]' => $this->toMinorUnits((float) $payment->amount, (string) $payment->currency),
                'line_items[0][price_data][product_data][name]' => 'Invoice #'.($invoice->number ?? $invoice->id),
                'client_reference_id' => (string) $payment->id,
                'metadata[payment_id]' => (string) $payment->id,
                'metadata[invoice_id]' => (string) $invoice->id,
                'metadata[tenant_id]' => (string) $payment->tenant_id,
                'payment_intent_data[metadata][payment_id]' => (string) $payment->id,
                'payment_intent_data[metadata][invoice_id]' => (string) $invoice->id,
            ]);

        if ($response->failed()) {
            return PaymentResult::failure(
                $response->json('error.message') ?? 'Stripe Checkout session failed.',
                '',
                $response->json() ?? [],
            );
        }

        $sessionId = (string) $response->json('id');
        $url = (string) $response->json('url');

        return PaymentResult::pending($sessionId, [
            'checkout_url' => $url,
            'session_id' => $sessionId,
            'payment_intent' => $response->json('payment_intent'),
            'provider' => 'stripe',
        ], 'Redirect the customer to Stripe Checkout to complete payment.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createSetupSession(array $options): SetupSessionResult
    {
        $secret = (string) config('payments.stripe.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Stripe is not configured. Set STRIPE_SECRET.');
        }

        $customerResponse = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->asForm()
            ->post('/v1/customers', [
                'email' => (string) $options['email'],
                'metadata[signup_intent_id]' => (string) ($options['metadata']['signup_intent_id'] ?? ''),
            ]);

        if ($customerResponse->failed()) {
            return SetupSessionResult::failure(
                $customerResponse->json('error.message') ?? 'Stripe customer create failed.',
                '',
                $customerResponse->json() ?? [],
            );
        }

        $customerId = (string) $customerResponse->json('id');

        $response = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->asForm()
            ->post('/v1/checkout/sessions', [
                'mode' => 'setup',
                'customer' => $customerId,
                'success_url' => (string) $options['success_url'],
                'cancel_url' => (string) $options['cancel_url'],
                'currency' => strtolower((string) $options['currency']),
                'client_reference_id' => (string) ($options['reference'] ?? ''),
                'metadata[signup_intent_id]' => (string) ($options['metadata']['signup_intent_id'] ?? ''),
                'metadata[purpose]' => 'signup_card_verification',
            ]);

        if ($response->failed()) {
            return SetupSessionResult::failure(
                $response->json('error.message') ?? 'Stripe setup session failed.',
                '',
                $response->json() ?? [],
            );
        }

        $sessionId = (string) $response->json('id');
        $url = (string) $response->json('url');

        return SetupSessionResult::success($sessionId, $url, [
            'session_id' => $sessionId,
            'customer_id' => $customerId,
            'setup_intent' => $response->json('setup_intent'),
            'provider' => 'stripe',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function confirmSetup(string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult
    {
        $secret = (string) config('payments.stripe.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Stripe is not configured. Set STRIPE_SECRET.', $reference);
        }

        $session = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->get('/v1/checkout/sessions/'.$reference, [
                'expand[]' => 'setup_intent',
            ]);

        if ($session->failed()) {
            return SetupSessionResult::failure(
                $session->json('error.message') ?? 'Stripe setup session lookup failed.',
                $reference,
                $session->json() ?? [],
            );
        }

        if ((string) $session->json('status') !== 'complete') {
            return SetupSessionResult::failure('Stripe setup session is not complete.', $reference, $session->json() ?? []);
        }

        $setupIntentId = (string) ($session->json('setup_intent.id') ?? $session->json('setup_intent') ?? '');
        $customerId = (string) ($session->json('customer') ?? '');

        if ($setupIntentId === '') {
            return SetupSessionResult::failure('Stripe setup intent missing.', $reference, $session->json() ?? []);
        }

        $setupIntent = $this->httpClient((string) config('payments.stripe.api_base'))
            ->withBasicAuth($secret, '')
            ->get('/v1/setup_intents/'.$setupIntentId, [
                'expand[]' => 'payment_method',
            ]);

        if ($setupIntent->failed() || (string) $setupIntent->json('status') !== 'succeeded') {
            return SetupSessionResult::failure(
                $setupIntent->json('error.message') ?? 'Stripe setup intent not succeeded.',
                $reference,
                $setupIntent->json() ?? [],
            );
        }

        $paymentMethodId = (string) ($setupIntent->json('payment_method.id') ?? $setupIntent->json('payment_method') ?? '');
        $card = $setupIntent->json('payment_method.card') ?? [];

        return new PaymentMethodPayload(
            gateway: 'stripe',
            externalId: $paymentMethodId !== '' ? $paymentMethodId : null,
            customerExternalId: $customerId !== '' ? $customerId : null,
            brand: is_array($card) ? ($card['brand'] ?? null) : null,
            lastFour: is_array($card) ? ($card['last4'] ?? null) : null,
            expMonth: is_array($card) && isset($card['exp_month']) ? (int) $card['exp_month'] : null,
            expYear: is_array($card) && isset($card['exp_year']) ? (int) $card['exp_year'] : null,
            meta: [
                'setup_intent' => $setupIntentId,
                'checkout_session' => $reference,
            ],
            shouldRefund: false,
            chargedAmount: 0,
            currency: strtoupper((string) ($options['currency'] ?? 'USD')),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeOffSession(Invoice $invoice, Payment $payment, PaymentMethod $method, array $options = []): PaymentResult
    {
        if (blank($method->external_id)) {
            return PaymentResult::failure('Stripe payment method is missing.');
        }

        return $this->charge($invoice, $payment, [
            ...$options,
            'payment_method' => $method->external_id,
        ]);
    }
}
