<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Models\Central\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Service responsible for verifying and applying payment provider webhooks.
 */
final class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentService $payments,
    )
    {
    }

    /**
     * Handle an inbound provider webhook payload.
     *
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     *
     * @throws AccessDeniedHttpException|ValidationException|Throwable
     */
    public function handle(string $gateway, Request $request): array
    {
        return match ($gateway) {
            PaymentGateway::STRIPE->value => $this->handleStripe($request),
            PaymentGateway::PAYSTACK->value => $this->handlePaystack($request),
            PaymentGateway::FLUTTERWAVE->value => $this->handleFlutterwave($request),
            default => throw ValidationException::withMessages([
                'gateway' => ["Webhook handling is not supported for [{$gateway}]."],
            ]),
        };
    }

    /**
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     */
    private function handleStripe(Request $request): array
    {
        $payload = $request->getContent();
        $signature = (string)$request->header('Stripe-Signature', '');
        $secret = (string)config('payments.stripe.webhook_secret');

        if ($secret !== '') {
            $this->verifyStripeSignature($payload, $signature, $secret);
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();
        $type = (string)($event['type'] ?? '');

        if ($type === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $paymentId = $session['metadata']['payment_id'] ?? $session['client_reference_id'] ?? null;
            $reference = (string)($session['payment_intent'] ?? $session['id'] ?? '');

            return $this->completeById($paymentId, $reference, $event);
        }

        if ($type === 'payment_intent.succeeded') {
            $intent = $event['data']['object'] ?? [];
            $paymentId = $intent['metadata']['payment_id'] ?? null;
            $reference = (string)($intent['id'] ?? '');

            return $this->completeById($paymentId, $reference, $event);
        }

        if ($type === 'payment_intent.payment_failed') {
            $intent = $event['data']['object'] ?? [];
            $paymentId = $intent['metadata']['payment_id'] ?? null;
            $message = (string)($intent['last_payment_error']['message'] ?? 'Stripe payment failed.');

            return $this->failById($paymentId, $message, $event);
        }

        return ['handled' => false];
    }

    private function verifyStripeSignature(string $payload, string $header, string $secret): void
    {
        if ($header === '') {
            throw new AccessDeniedHttpException('Missing Stripe signature.');
        }

        $parts = [];
        foreach (explode(',', $header) as $item) {
            [$k, $v] = array_pad(explode('=', $item, 2), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k][] = $v;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === []) {
            throw new AccessDeniedHttpException('Malformed Stripe signature.');
        }

        if (abs(time() - (int)$timestamp) > 300) {
            throw new AccessDeniedHttpException('Expired Stripe signature.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Invalid Stripe signature.');
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{handled: bool, payment_id: int|null, status: string}
     */
    private function completeById(mixed $paymentId, string $reference, array $raw): array
    {
        $payment = $this->findPayment($paymentId, $reference);

        if ($payment === null) {
            Log::warning('Payment webhook could not resolve payment.', ['reference' => $reference, 'payment_id' => $paymentId]);

            return ['handled' => false, 'payment_id' => null, 'status' => 'ignored'];
        }

        $this->payments->completePayment($payment, $reference !== '' ? $reference : (string)$payment->gateway_reference, $raw);

        return ['handled' => true, 'payment_id' => $payment->id, 'status' => 'completed'];
    }

    private function findPayment(mixed $paymentId, ?string $reference): ?Payment
    {
        if (filled($paymentId)) {
            return Payment::query()->find($paymentId);
        }

        if (filled($reference)) {
            return Payment::query()->where('gateway_reference', $reference)->first();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{handled: bool, payment_id: int|null, status: string}
     */
    private function failById(mixed $paymentId, string $message, array $raw): array
    {
        $payment = $this->findPayment($paymentId, null);

        if ($payment === null) {
            return ['handled' => false, 'payment_id' => null, 'status' => 'ignored'];
        }

        $this->payments->failPayment($payment, $message, $raw);

        return ['handled' => true, 'payment_id' => $payment->id, 'status' => 'failed'];
    }

    /**
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     */
    private function handlePaystack(Request $request): array
    {
        $secret = (string)(config('payments.paystack.webhook_secret') ?: config('payments.paystack.secret'));
        $signature = (string)$request->header('x-paystack-signature', '');

        if ($secret !== '') {
            $computed = hash_hmac('sha512', $request->getContent(), $secret);

            if (!hash_equals($computed, $signature)) {
                throw new AccessDeniedHttpException('Invalid Paystack webhook signature.');
            }
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();
        $type = (string)($event['event'] ?? '');
        $data = $event['data'] ?? [];

        if ($type === 'charge.success') {
            $paymentId = $data['metadata']['payment_id'] ?? null;
            $reference = (string)($data['reference'] ?? '');

            if ($reference !== '') {
                $this->verifyPaystackTransaction($reference);
            }

            return $this->completeById($paymentId, $reference !== '' ? $reference : (string)($data['id'] ?? ''), $event);
        }

        if (in_array($type, ['charge.failed', 'paymentrequest.failed'], true)) {
            $paymentId = $data['metadata']['payment_id'] ?? null;

            return $this->failById($paymentId, (string)($data['gateway_response'] ?? 'Paystack charge failed.'), $event);
        }

        return ['handled' => false];
    }

    private function verifyPaystackTransaction(string $reference): void
    {
        $secret = (string)config('payments.paystack.secret');

        if ($secret === '') {
            return;
        }

        $response = Http::baseUrl((string)config('payments.paystack.api_base'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withToken($secret)
            ->get('/transaction/verify/' . $reference);

        if ($response->failed() || $response->json('data.status') !== 'success') {
            throw ValidationException::withMessages([
                'payment' => ['Paystack transaction verification failed.'],
            ]);
        }
    }

    /**
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     */
    private function handleFlutterwave(Request $request): array
    {
        $secret = (string)config('payments.flutterwave.webhook_secret');
        $signature = (string)$request->header('verif-hash', '');

        if ($secret !== '' && !hash_equals($secret, $signature)) {
            throw new AccessDeniedHttpException('Invalid Flutterwave webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();
        $data = $event['data'] ?? $event;
        $status = Str::lower((string)($data['status'] ?? $event['status'] ?? ''));
        $txRef = (string)($data['tx_ref'] ?? $data['txRef'] ?? '');
        $paymentId = $data['meta']['payment_id'] ?? $data['meta']['paymentId'] ?? null;

        if ($paymentId === null && $txRef !== '') {
            $payment = Payment::query()->where('gateway_reference', $txRef)->first();
            $paymentId = $payment?->id;
        }

        if (in_array($status, ['successful', 'success'], true)) {
            if ($txRef !== '') {
                $this->verifyFlutterwaveTransaction($txRef);
            }

            return $this->completeById($paymentId, $txRef !== '' ? $txRef : (string)($data['id'] ?? ''), $event);
        }

        if (in_array($status, ['failed', 'cancelled'], true)) {
            return $this->failById($paymentId, 'Flutterwave payment ' . $status . '.', $event);
        }

        return ['handled' => false];
    }

    private function verifyFlutterwaveTransaction(string $txRef): void
    {
        $secret = (string)config('payments.flutterwave.secret');

        if ($secret === '') {
            return;
        }

        $response = Http::baseUrl((string)config('payments.flutterwave.api_base'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withToken($secret)
            ->get('/transactions/verify_by_reference', ['tx_ref' => $txRef]);

        $status = $response->json('data.status');

        if ($response->failed() || !in_array($status, ['successful', 'success'], true)) {
            throw ValidationException::withMessages([
                'payment' => ['Flutterwave transaction verification failed.'],
            ]);
        }
    }
}
