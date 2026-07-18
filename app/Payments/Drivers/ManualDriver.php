<?php

declare(strict_types=1);

namespace App\Payments\Drivers;

/**
 * Manual / bank-transfer gateway used for offline settlement flows.
 */
final class ManualDriver extends AbstractGatewayDriver
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'manual';
    }

    /**
     * Manual payments are not recurring.
     */
    public function supportsRecurring(): bool
    {
        return false;
    }
}
