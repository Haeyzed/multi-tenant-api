<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

/**
 * PayPal gateway stub (not enabled until API wiring lands).
 */
final class PayPalDriver extends UnimplementedLiveGatewayDriver
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'paypal';
    }
}
