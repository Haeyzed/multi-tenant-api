<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Services\Central\Billing\PaymentGatewayConfigService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:sync-payment-gateway-configs')]
#[Description('Synchronize legacy billing settings into gateway configuration records')]
final class SyncPaymentGatewayConfigsCommand extends Command
{
    public function __construct(private readonly PaymentGatewayConfigService $gatewayConfigs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->gatewayConfigs->syncFromSettings();
        $this->info('Payment gateway configuration sync completed.');

        return self::SUCCESS;
    }
}
