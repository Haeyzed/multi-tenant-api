<?php

declare(strict_types=1);

namespace App\Mail\Tenant;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminds a tenant owner that their trial is ending soon.
 */
class TrialEndingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $planName,
        public int $daysLeft,
        public CarbonInterface $trialEndsAt,
        public string $checkoutUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->tenantName} trial ends in {$this->daysLeft} day(s)",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tenant.trial-ending',
        );
    }
}
