<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Services\Central\Billing\TrialBillingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily processor for trial reminders and trial-ended conversions.
 */
#[Signature('billing:process-trials')]
#[Description('Send trial-ending reminders and convert ended trials to past due with invoices')]
class ProcessTrialsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TrialBillingService $trialBillingService): int
    {
        $result = $trialBillingService->processTrials();

        $this->info("Trial reminders sent: {$result['reminders']}");
        $this->info("Ended trials processed: {$result['ended']}");

        return self::SUCCESS;
    }
}
