<?php

declare(strict_types=1);

namespace App\Mail\Central;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.central.settings-test-text',
        );
    }
}
