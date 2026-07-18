<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentStatus;
use App\Models\Central\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Handles browser return URLs after hosted payment checkout.
 *
 * Completes payments when the user lands on the success callback even if the
 * webhook has not arrived yet (gateway-aware verification).
 */
final class BillingReturnService
{
    public function __construct(
        private readonly PaymentService $payments,
    )
    {
    }

    /**
     * Resolve and optionally finalize a payment from the success redirect query.
     *
     * @param array{payment?: mixed, reference?: mixed, trxref?: mixed, transaction_id?: mixed, status?: mixed} $query
     * @return array{payment: Payment|null, message: string, completed: bool}
     */
    public function handleSuccess(array $query): array
    {
        $paymentId = is_numeric($query['payment'] ?? null) ? (int)$query['payment'] : null;
        $reference = (string)($query['reference'] ?? $query['trxref'] ?? $query['transaction_id'] ?? '');

        $payment = $this->findPayment($paymentId, $reference !== '' ? $reference : null);

        if ($payment === null) {
            return [
                'payment' => null,
                'message' => 'Payment record was not found. If you were charged, contact support with your reference.',
                'completed' => false,
            ];
        }

        if ($payment->status === PaymentStatus::COMPLETED) {
            return [
                'payment' => $payment->fresh(['invoice', 'subscription.tenant', 'subscription.plan']),
                'message' => 'Payment confirmed. Your subscription is active.',
                'completed' => true,
            ];
        }

        $reference = $reference !== '' ? $reference : (string)$payment->gateway_reference;

        if ($reference === '') {
            return [
                'payment' => $payment,
                'message' => 'Payment is still processing. Waiting for provider confirmation.',
                'completed' => false,
            ];
        }

        try {
            $raw = $this->verifyWithGateway($payment, $reference, $query);
            $payment = $this->payments->completePayment($payment, $reference, $raw);

            return [
                'payment' => $payment->fresh(['invoice', 'subscription.tenant', 'subscription.plan']),
                'message' => 'Payment confirmed. Your subscription is active.',
                'completed' => true,
            ];
        } catch (ValidationException $e) {
            return [
                'payment' => $payment->fresh(['invoice', 'subscription']),
                'message' => $e->getMessage() !== ''
                    ? collect($e->errors())->flatten()->first() ?? 'Payment could not be verified yet.'
                    : 'Payment could not be verified yet.',
                'completed' => false,
            ];
        }
    }

    private function findPayment(?int $paymentId, ?string $reference): ?Payment
    {
        if ($paymentId !== null) {
            return Payment::query()->find($paymentId);
        }

        if (filled($reference)) {
            return Payment::query()->where('gateway_reference', $reference)->first();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function verifyWithGateway(Payment $payment, string $reference, array $query): array
    {
        return match ($payment->gateway) {
            PaymentGateway::PAYSTACK => $this->verifyPaystack($reference),
            PaymentGateway::FLUTTERWAVE => $this->verifyFlutterwave($reference),
            PaymentGateway::STRIPE => $this->verifyStripe($reference, $query),
            default => [
                'source' => 'return_url',
                'gateway' => $payment->gateway?->value,
                'reference' => $reference,
                'note' => 'Accepted on return URL; webhook remains source of truth for this gateway.',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function verifyPaystack(string $reference): array
    {
        $secret = (string)config('payments.paystack.secret');

        if ($secret === '') {
            throw ValidationException::withMessages([
                'payment' => ['Paystack is not configured.'],
            ]);
        }

        $response = Http::baseUrl((string)config('payments.paystack.api_base'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withToken($secret)
            ->get('/transaction/verify/' . $reference);

        if ($response->failed() || $response->json('data.status') !== 'success') {
            throw ValidationException::withMessages([
                'payment' => [$response->json('message') ?? 'Paystack transaction verification failed.'],
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function verifyFlutterwave(string $reference): array
    {
        $secret = (string)config('payments.flutterwave.secret');

        if ($secret === '') {
            throw ValidationException::withMessages([
                'payment' => ['Flutterwave is not configured.'],
            ]);
        }

        $response = Http::baseUrl((string)config('payments.flutterwave.api_base', 'https://api.flutterwave.com/v3'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withToken($secret)
            ->get('/transactions/verify_by_reference', [
                'tx_ref' => $reference,
            ]);

        $status = strtolower((string)($response->json('data.status') ?? ''));

        if ($response->failed() || !in_array($status, ['successful', 'success'], true)) {
            throw ValidationException::withMessages([
                'payment' => [$response->json('message') ?? 'Flutterwave transaction verification failed.'],
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function verifyStripe(string $reference, array $query): array
    {
        $secret = (string)config('payments.stripe.secret');

        if ($secret === '') {
            // Missing secret: accept return when status hints success.
            if (in_array(strtolower((string)($query['status'] ?? 'success')), ['success', 'paid', 'complete', 'completed'], true)) {
                return [
                    'source' => 'return_url',
                    'gateway' => 'stripe',
                    'reference' => $reference,
                    'status' => $query['status'] ?? 'success',
                ];
            }

            throw ValidationException::withMessages([
                'payment' => ['Stripe is not configured.'],
            ]);
        }

        $response = Http::baseUrl((string)config('payments.stripe.api_base', 'https://api.stripe.com'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withToken($secret)
            ->get('/v1/checkout/sessions/' . $reference);

        if ($response->failed()) {
            // Fallback: payment intents / generic session id may live as payment_intent.
            $intent = Http::baseUrl((string)config('payments.stripe.api_base', 'https://api.stripe.com'))
                ->timeout(15)
                ->connectTimeout(5)
                ->withToken($secret)
                ->get('/v1/payment_intents/' . $reference);

            if ($intent->failed() || ($intent->json('status') !== 'succeeded')) {
                throw ValidationException::withMessages([
                    'payment' => [$response->json('error.message') ?? 'Stripe payment verification failed.'],
                ]);
            }

            return $intent->json() ?? [];
        }

        $paymentStatus = (string)($response->json('payment_status') ?? '');

        if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            throw ValidationException::withMessages([
                'payment' => ['Stripe checkout session is not paid yet.'],
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * @return array{payment: Payment|null, message: string}
     */
    public function handleCancel(array $query): array
    {
        $paymentId = is_numeric($query['payment'] ?? null) ? (int)$query['payment'] : null;
        $payment = $paymentId ? Payment::query()->find($paymentId) : null;

        return [
            'payment' => $payment,
            'message' => 'Checkout was cancelled. You can retry subscribe/pay from your trial email link.',
        ];
    }
}
