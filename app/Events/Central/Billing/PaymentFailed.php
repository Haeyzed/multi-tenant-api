<?php

declare(strict_types=1);

namespace App\Events\Central\Billing;

use App\Models\Central\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a payment is marked as failed.
 */
final class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Payment  $payment  Failed payment record.
     * @param  string  $message  Provider failure message.
     */
    public function __construct(
        public Payment $payment,
        public string $message,
    ) {}
}
