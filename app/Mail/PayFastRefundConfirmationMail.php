<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the player when a PayFast refund has been successfully processed.
 */
class PayFastRefundConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CategoryEventRegistration $registration;

    public function __construct(CategoryEventRegistration $registration)
    {
        $this->registration = $registration;
    }

    public function envelope(): Envelope
    {
        $eventName = optional($this->registration->categoryEvent?->event)->name ?? 'Event';

        return new Envelope(
            subject: 'Refund Processed – ' . $eventName
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.refund.payfast-confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
