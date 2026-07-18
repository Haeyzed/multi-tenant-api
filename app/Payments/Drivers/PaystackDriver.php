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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live Paystack transaction driver (test or live credentials).
 */
final class PaystackDriver extends AbstractGatewayDriver
{
    use InteractsWithPaymentHttp;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'paystack';
    }

    /**
     * Initialize a Paystack transaction or charge an authorization.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult
    {
        if (($options['force_failure'] ?? false) === true) {
            return PaymentResult::failure('Paystack charge declined.', '', ['forced' => true]);
        }

        $secret = (string) config('payments.paystack.secret');

        if ($secret === '') {
            return PaymentResult::failure('Paystack is not configured. Set PAYSTACK_SECRET.');
        }

        try {
            if (filled($options['authorization_code'] ?? null)) {
                return $this->chargeAuthorization($invoice, $payment, $options, $secret);
            }

            return $this->initializeTransaction($invoice, $payment, $secret);
        } catch (Throwable $e) {
            return PaymentResult::failure($e->getMessage(), '', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Refund via Paystack Refund API.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ConnectionException
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult
    {
        $secret = (string) config('payments.paystack.secret');

        if ($secret === '' || blank($payment->gateway_reference)) {
            return PaymentResult::failure('Paystack refund requires PAYSTACK_SECRET and a gateway reference.');
        }

        $response = $this->httpClient((string) config('payments.paystack.api_base'))
            ->withToken($secret)
            ->post('/refund', [
                'transaction' => $payment->gateway_reference,
                'amount' => $this->toMinorUnits($amount, (string) $payment->currency),
                'merchant_note' => $options['reason'] ?? 'Refund requested',
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            return PaymentResult::failure(
                $response->json('message') ?? 'Paystack refund failed.',
                '',
                $response->json() ?? [],
            );
        }

        return PaymentResult::success(
            (string) ($response->json('data.id') ?? $this->reference('re')),
            'refunded',
            $response->json() ?? [],
        );
    }

    /**
     * Charge a stored Paystack authorization code.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ConnectionException
     */
    private function chargeAuthorization(Invoice $invoice, Payment $payment, array $options, string $secret): PaymentResult
    {
        $reference = 'PSK_'.Str::upper(Str::random(20));

        $response = $this->httpClient((string) config('payments.paystack.api_base'))
            ->withToken($secret)
            ->post('/transaction/charge_authorization', [
                'authorization_code' => $options['authorization_code'],
                'email' => $this->customerEmail($invoice),
                'amount' => $this->toMinorUnits((float) $payment->amount, (string) $payment->currency),
                'currency' => Str::upper((string) $payment->currency),
                'reference' => $reference,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $payment->tenant_id,
                ],
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            return PaymentResult::failure(
                $response->json('message') ?? 'Paystack authorization charge failed.',
                $reference,
                $response->json() ?? [],
            );
        }

        $status = (string) $response->json('data.status');
        $ref = (string) ($response->json('data.reference') ?? $reference);

        if ($status === 'success') {
            return PaymentResult::success($ref, 'completed', $response->json() ?? []);
        }

        return PaymentResult::pending($ref, $response->json() ?? [], 'Paystack charge is pending confirmation.');
    }

    /**
     * Initialize a hosted Paystack transaction and return checkout_url.
     */
    private function initializeTransaction(Invoice $invoice, Payment $payment, string $secret): PaymentResult
    {
        $reference = 'PSK_'.Str::upper(Str::random(20));

        $response = $this->httpClient((string) config('payments.paystack.api_base'))
            ->withToken($secret)
            ->post('/transaction/initialize', [
                'email' => $this->customerEmail($invoice),
                'amount' => $this->toMinorUnits((float) $payment->amount, (string) $payment->currency),
                'currency' => Str::upper((string) $payment->currency),
                'reference' => $reference,
                'callback_url' => $this->redirectUrl('success_url', $payment),
                'metadata' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $payment->tenant_id,
                    'cancel_action' => $this->redirectUrl('cancel_url', $payment),
                ],
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            return PaymentResult::failure(
                $response->json('message') ?? 'Paystack initialize failed.',
                $reference,
                $response->json() ?? [],
            );
        }

        return PaymentResult::pending($reference, [
            'checkout_url' => $response->json('data.authorization_url'),
            'access_code' => $response->json('data.access_code'),
            'reference' => $reference,
            'provider' => 'paystack',
        ], 'Redirect the customer to Paystack to complete payment.');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createSetupSession(array $options): SetupSessionResult
    {
        $secret = (string) config('payments.paystack.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Paystack is not configured. Set PAYSTACK_SECRET.');
        }

        $reference = (string) ($options['reference'] ?? ('PSK_SETUP_'.Str::upper(Str::random(16))));
        $currency = Str::upper((string) ($options['currency'] ?? 'NGN'));
        // Paystack rejects very small NGN amounts (e.g. ₦1) with a misleading
        // "No active channel" error; require at least ₦5 for soft verify.
        $minimum = $currency === 'NGN' ? 5.0 : 0.01;
        $amount = max($minimum, (float) ($options['amount'] ?? $minimum));

        $response = $this->httpClient((string) config('payments.paystack.api_base'))
            ->withToken($secret)
            ->post('/transaction/initialize', [
                'email' => (string) $options['email'],
                'amount' => $this->toMinorUnits($amount, $currency),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => (string) $options['success_url'],
                'channels' => ['card'],
                'metadata' => array_merge($options['metadata'] ?? [], [
                    'purpose' => 'signup_card_verification',
                    'cancel_action' => (string) $options['cancel_url'],
                ]),
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            return SetupSessionResult::failure(
                $response->json('message') ?? 'Paystack setup initialize failed.',
                $reference,
                $response->json() ?? [],
            );
        }

        return SetupSessionResult::success($reference, (string) $response->json('data.authorization_url'), [
            'access_code' => $response->json('data.access_code'),
            'reference' => $reference,
            'provider' => 'paystack',
            'amount' => $amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function confirmSetup(string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult
    {
        $secret = (string) config('payments.paystack.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Paystack is not configured. Set PAYSTACK_SECRET.', $reference);
        }

        $response = $this->httpClient((string) config('payments.paystack.api_base'))
            ->withToken($secret)
            ->get('/transaction/verify/'.$reference);

        if ($response->failed() || $response->json('status') !== true) {
            return SetupSessionResult::failure(
                $response->json('message') ?? 'Paystack verification failed.',
                $reference,
                $response->json() ?? [],
            );
        }

        $status = (string) $response->json('data.status');

        if ($status !== 'success') {
            return SetupSessionResult::failure('Paystack transaction was not successful.', $reference, $response->json() ?? []);
        }

        $authorization = $response->json('data.authorization') ?? [];
        $amountMinor = (int) ($response->json('data.amount') ?? 0);
        $currency = Str::upper((string) ($response->json('data.currency') ?? ($options['currency'] ?? 'NGN')));
        $charged = $amountMinor > 0
            ? (in_array($currency, config('payments.zero_decimal_currencies', []), true)
                ? (float) $amountMinor
                : $amountMinor / 100)
            : 0.0;

        $shouldRefund = (bool) ($options['refund'] ?? true) && $charged > 0;

        return new PaymentMethodPayload(
            gateway: 'paystack',
            authorizationCode: is_array($authorization) ? ($authorization['authorization_code'] ?? null) : null,
            brand: is_array($authorization) ? ($authorization['brand'] ?? $authorization['card_type'] ?? null) : null,
            lastFour: is_array($authorization) ? ($authorization['last4'] ?? null) : null,
            expMonth: is_array($authorization) && isset($authorization['exp_month']) ? (int) $authorization['exp_month'] : null,
            expYear: is_array($authorization) && isset($authorization['exp_year']) ? (int) $authorization['exp_year'] : null,
            meta: [
                'reference' => $reference,
                'authorization' => $authorization,
                'customer' => $response->json('data.customer'),
            ],
            refundReference: $reference,
            shouldRefund: $shouldRefund,
            chargedAmount: $charged,
            currency: $currency,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeOffSession(Invoice $invoice, Payment $payment, PaymentMethod $method, array $options = []): PaymentResult
    {
        if (blank($method->authorization_code)) {
            return PaymentResult::failure('Paystack authorization code is missing.');
        }

        return $this->charge($invoice, $payment, [
            ...$options,
            'authorization_code' => $method->authorization_code,
        ]);
    }
}
