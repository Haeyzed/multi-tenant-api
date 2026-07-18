<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Payments\PaymentResult;
use App\Payments\Support\InteractsWithPaymentHttp;

/**
 * Placeholder gateways that are not wired to provider APIs yet.
 */
abstract class UnimplementedLiveGatewayDriver extends AbstractGatewayDriver
{
    use InteractsWithPaymentHttp;

    /**
     * @param  array<string, mixed>  $options
     */
    public function charge(Invoice $invoice, Payment $payment, array $options = []): PaymentResult
    {
        return PaymentResult::failure(
            "{$this->name()} payments are not enabled yet. Use stripe, paystack, or flutterwave.",
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function refund(Payment $payment, float $amount, array $options = []): PaymentResult
    {
        return PaymentResult::failure(
            "{$this->name()} refunds are not enabled yet.",
        );
    }
}
