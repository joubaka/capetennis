<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WebsiteErrorAlertMail extends Mailable
{
    public function __construct(public array $diagnostic) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cape Tennis website error ['.$this->diagnostic['environment'].']');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.website-error-alert');
    }
}
