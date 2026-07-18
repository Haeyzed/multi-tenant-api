<?php

declare(strict_types=1);

namespace App\Mail\Tenant;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeTenantOwner extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $primaryDomain,
        public string $setupUrl,
        public CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->tenantName} — set your password",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tenant.welcome-owner',
        );
    }
}
