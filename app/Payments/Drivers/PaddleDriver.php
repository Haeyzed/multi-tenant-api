<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

/**
 * Paddle gateway stub (not enabled until API wiring lands).
 */
final class PaddleDriver extends UnimplementedLiveGatewayDriver
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'paddle';
    }

    /**
     * Paddle refunds are not modelled in simulation.
     */
    public function supportsRefunds(): bool
    {
        return false;
    }
}
