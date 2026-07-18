<?php

declare(strict_types=1);

namespace App\Mail\Central;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a signed public invoice payment link to a customer.
 */
class InvoicePaymentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $invoiceNumber,
        public string $tenantName,
        public string $amount,
        public string $currency,
        public string $paymentUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoiceNumber} — payment due",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.central.invoice-payment',
        );
    }
}
