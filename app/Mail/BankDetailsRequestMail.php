<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BankDetailsRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CategoryEventRegistration $registration;
    public string $signedUrl;

    public function __construct(CategoryEventRegistration $registration)
    {
        $this->registration = $registration;

        // Signed URL valid for 7 days — no login required
        $this->signedUrl = URL::temporarySignedRoute(
            'refund.bank-details.show',
            now()->addDays(7),
            ['registration' => $registration->id]
        );
    }

    public function envelope(): Envelope
    {
        $eventName = optional($this->registration->categoryEvent?->event)->name ?? 'Event';

        return new Envelope(
            subject: 'Action Required: Bank Details for Refund – ' . $eventName
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.refund.bank-details-request');
    }

    public function attachments(): array
    {
        return [];
    }
}
