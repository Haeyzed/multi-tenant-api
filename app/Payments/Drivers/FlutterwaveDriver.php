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
 * Live Flutterwave standard payment driver (test or live credentials).
 */
final class FlutterwaveDriver extends AbstractGatewayDriver
{
    use InteractsWithPaymentHttp;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'flutterwave';
    }

    /**
     * Create a Flutterwave payment link.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult
    {
        if (($options['force_failure'] ?? false) === true) {
            return PaymentResult::failure('Flutterwave charge declined.', '', ['forced' => true]);
        }

        $secret = (string) config('payments.flutterwave.secret');

        if ($secret === '') {
            return PaymentResult::failure('Flutterwave is not configured. Set FLUTTERWAVE_SECRET.');
        }

        try {
            return $this->createPaymentLink($invoice, $payment, $secret);
        } catch (Throwable $e) {
            return PaymentResult::failure($e->getMessage(), '', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Refund via Flutterwave.
     *
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult
    {
        $secret = (string) config('payments.flutterwave.secret');

        if ($secret === '' || blank($payment->gateway_reference)) {
            return PaymentResult::failure('Flutterwave refund requires FLUTTERWAVE_SECRET and a gateway reference.');
        }

        $response = $this->httpClient((string) config('payments.flutterwave.api_base'))
            ->withToken($secret)
            ->post('/transactions/'.$payment->gateway_reference.'/refund', [
                'amount' => $amount,
                'comments' => $options['reason'] ?? 'Refund requested',
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            return PaymentResult::failure(
                $response->json('message') ?? 'Flutterwave refund failed.',
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
     * Create a hosted Flutterwave payment link and return checkout_url.
     */
    private function createPaymentLink(Invoice $invoice, Payment $payment, string $secret): PaymentResult
    {
        $txRef = 'FLW_'.Str::upper(Str::random(20));

        $response = $this->httpClient((string) config('payments.flutterwave.api_base'))
            ->withToken($secret)
            ->post('/payments', [
                'tx_ref' => $txRef,
                'amount' => (float) $payment->amount,
                'currency' => Str::upper((string) $payment->currency),
                'redirect_url' => $this->redirectUrl('success_url', $payment),
                'customer' => [
                    'email' => $this->customerEmail($invoice),
                    'name' => $invoice->tenant?->name ?? 'Tenant',
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => 'Invoice #'.($invoice->number ?? $invoice->id),
                ],
                'meta' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $payment->tenant_id,
                ],
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            return PaymentResult::failure(
                $response->json('message') ?? 'Flutterwave payment init failed.',
                $txRef,
                $response->json() ?? [],
            );
        }

        return PaymentResult::pending($txRef, [
            'checkout_url' => $response->json('data.link'),
            'tx_ref' => $txRef,
            'provider' => 'flutterwave',
        ], 'Redirect the customer to Flutterwave to complete payment.');
    }

    /**
     * @param array<string, mixed> $options
     * @throws ConnectionException
     */
    public function createSetupSession(array $options): SetupSessionResult
    {
        $secret = (string) config('payments.flutterwave.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Flutterwave is not configured. Set FLUTTERWAVE_SECRET.');
        }

        $txRef = (string) ($options['reference'] ?? ('FLW_SETUP_'.Str::upper(Str::random(16))));
        $amount = max(0.01, (float) ($options['amount'] ?? 1));

        $response = $this->httpClient((string) config('payments.flutterwave.api_base'))
            ->withToken($secret)
            ->post('/payments', [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => Str::upper((string) $options['currency']),
                'redirect_url' => (string) $options['success_url'],
                'customer' => [
                    'email' => (string) $options['email'],
                    'name' => (string) ($options['metadata']['name'] ?? 'Customer'),
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => 'Card verification',
                ],
                'meta' => array_merge($options['metadata'] ?? [], [
                    'purpose' => 'signup_card_verification',
                ]),
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            return SetupSessionResult::failure(
                $response->json('message') ?? 'Flutterwave setup init failed.',
                $txRef,
                $response->json() ?? [],
            );
        }

        return SetupSessionResult::success($txRef, (string) $response->json('data.link'), [
            'tx_ref' => $txRef,
            'provider' => 'flutterwave',
            'amount' => $amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function confirmSetup(string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult
    {
        $secret = (string) config('payments.flutterwave.secret');

        if ($secret === '') {
            return SetupSessionResult::failure('Flutterwave is not configured. Set FLUTTERWAVE_SECRET.', $reference);
        }

        $transactionId = (string) ($options['transaction_id'] ?? $options['id'] ?? '');

        if ($transactionId !== '') {
            $response = $this->httpClient((string) config('payments.flutterwave.api_base'))
                ->withToken($secret)
                ->get('/transactions/'.$transactionId.'/verify');
        } else {
            $response = $this->httpClient((string) config('payments.flutterwave.api_base'))
                ->withToken($secret)
                ->get('/transactions/verify_by_reference', [
                    'tx_ref' => $reference,
                ]);
        }

        if ($response->failed() || $response->json('status') !== 'success') {
            return SetupSessionResult::failure(
                $response->json('message') ?? 'Flutterwave verification failed.',
                $reference,
                $response->json() ?? [],
            );
        }

        $data = $response->json('data') ?? [];
        $status = (string) ($data['status'] ?? '');

        if (! in_array($status, ['successful', 'success'], true)) {
            return SetupSessionResult::failure('Flutterwave transaction was not successful.', $reference, $response->json() ?? []);
        }

        $card = is_array($data['card'] ?? null) ? $data['card'] : [];
        $charged = (float) ($data['amount'] ?? 0);
        $currency = Str::upper((string) ($data['currency'] ?? ($options['currency'] ?? 'NGN')));
        $shouldRefund = (bool) ($options['refund'] ?? true) && $charged > 0;
        $token = (string) ($card['token'] ?? $data['card']['token'] ?? '');

        return new PaymentMethodPayload(
            gateway: 'flutterwave',
            externalId: $token !== '' ? $token : (string) ($data['id'] ?? $reference),
            authorizationCode: $token !== '' ? $token : null,
            brand: $card['type'] ?? $card['issuer'] ?? null,
            lastFour: $card['last_4digits'] ?? $card['last4'] ?? null,
            expMonth: isset($card['expiry']) ? (int) explode('/', (string) $card['expiry'])[0] : null,
            expYear: isset($card['expiry']) ? (int) ('20'.explode('/', (string) $card['expiry'])[1]) : null,
            meta: [
                'tx_ref' => $reference,
                'transaction_id' => $data['id'] ?? $transactionId,
                'card' => $card,
                'customer' => $data['customer'] ?? null,
            ],
            refundReference: (string) ($data['id'] ?? $reference),
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
        return PaymentResult::failure(
            'Flutterwave off-session token charges are not enabled yet. Use hosted checkout.',
        );
    }
}
