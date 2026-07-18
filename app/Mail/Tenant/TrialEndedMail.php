<?php

declare(strict_types=1);

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies a tenant owner that their trial has ended and payment is required.
 */
class TrialEndedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $planName,
        public string $checkoutUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->tenantName} trial has ended — subscribe to continue",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tenant.trial-ended',
        );
    }
}
