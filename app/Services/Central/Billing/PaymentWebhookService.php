<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Models\Central\Payment;
use App\Payments\DTOs\WebhookResult;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Thin webhook orchestrator that delegates parse/verify to gateway drivers.
 */
final class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * Handle an inbound provider webhook payload.
     *
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     *
     * @throws ValidationException|Throwable
     */
    public function handle(string $gateway, Request $request): array
    {
        if (PaymentGateway::tryFrom($gateway) === null) {
            throw ValidationException::withMessages([
                'gateway' => ["Webhook handling is not supported for [{$gateway}]."],
            ]);
        }

        $result = $this->gateways->driver($gateway)->handleWebhook($request);

        return $this->apply($result);
    }

    /**
     * @return array{handled: bool, payment_id?: int|null, status?: string}
     */
    private function apply(WebhookResult $result): array
    {
        if (! $result->handled || $result->paymentId === null) {
            if ($result->reference !== null) {
                Log::warning('Payment webhook could not resolve payment.', [
                    'reference' => $result->reference,
                    'payment_id' => $result->paymentId,
                ]);
            }

            return [
                'handled' => false,
                'payment_id' => $result->paymentId,
                'status' => $result->status,
            ];
        }

        $payment = Payment::query()->find($result->paymentId);

        if ($payment === null) {
            return ['handled' => false, 'payment_id' => null, 'status' => 'ignored'];
        }

        if ($result->status === 'failed') {
            $this->payments->failPayment(
                $payment,
                $result->message ?? 'Payment failed.',
                $result->raw,
            );

            return ['handled' => true, 'payment_id' => $payment->id, 'status' => 'failed'];
        }

        if ($result->status === 'completed') {
            $this->payments->completePayment(
                $payment,
                $result->reference !== null && $result->reference !== ''
                    ? $result->reference
                    : (string) $payment->gateway_reference,
                $result->raw,
            );

            return ['handled' => true, 'payment_id' => $payment->id, 'status' => 'completed'];
        }

        return ['handled' => false, 'payment_id' => $payment->id, 'status' => 'ignored'];
    }
}
