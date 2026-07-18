<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

/**
 * Lemon Squeezy gateway stub (not enabled until API wiring lands).
 */
final class LemonSqueezyDriver extends UnimplementedLiveGatewayDriver
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'lemon_squeezy';
    }
}
