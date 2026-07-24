<?php

declare(strict_types=1);

use App\Services\Central\Billing\BillingService;

it('delegates gateway resolution through the billing facade', function (): void {
    $billing = app(BillingService::class);

    expect($billing->resolveGateway(explicitGateway: 'manual'))->toBe('manual');
});
