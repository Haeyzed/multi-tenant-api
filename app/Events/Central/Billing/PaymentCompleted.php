<?php

declare(strict_types=1);

namespace App\Events\Central\Billing;

use App\Models\Central\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a payment has been completed successfully.
 */
final class PaymentCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Payment  $payment  Completed payment record.
     */
    public function __construct(
        public Payment $payment,
    ) {}
}
