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
 * Sent to the player when an admin cancels a withdrawal and reinstates
 * the registration back to active.
 */
class RegistrationReinstatedMail extends Mailable implements ShouldQueue
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
            subject: 'Registration Reinstated – ' . $eventName
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.withdrawal.reinstated');
    }

    public function attachments(): array
    {
        return [];
    }
}
