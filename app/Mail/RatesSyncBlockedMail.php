<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RatesSyncBlockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $reason)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.rates_blocked_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rates-sync-blocked',
        );
    }
}
