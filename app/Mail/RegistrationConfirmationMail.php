<?php

namespace App\Mail;

use App\Models\RegistrationOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public RegistrationOrder $order;

    public function __construct(RegistrationOrder $order)
    {
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        $eventName = optional($this->order->items->first()?->category_event?->event)->name ?? 'Event';

        return new Envelope(
            subject: 'Registration Confirmation – ' . $eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.registration.confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
