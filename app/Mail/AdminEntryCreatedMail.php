<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminEntryCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CategoryEventRegistration $entry)
    {
    }

    public function envelope(): Envelope
    {
        $event = $this->entry->categoryEvent?->event?->name ?? 'Event';

        return new Envelope(subject: "Registration Confirmed - {$event}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.registration.admin-created');
    }
}
