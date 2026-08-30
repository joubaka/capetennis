<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailureAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Cape Tennis] Payment/registration failure: ' . ($this->details['operation'] ?? 'unknown'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-failure-alert');
    }

    public function attachments(): array
    {
        return [];
    }
}
