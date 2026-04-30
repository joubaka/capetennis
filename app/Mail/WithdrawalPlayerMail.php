<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalPlayerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CategoryEventRegistration $registration;

    /** 'self' | 'admin' */
    public string $initiatedBy;

    public function __construct(CategoryEventRegistration $registration, string $initiatedBy = 'self')
    {
        $this->registration  = $registration;
        $this->initiatedBy   = $initiatedBy;
    }

    public function envelope(): Envelope
    {
        $eventName = optional($this->registration->categoryEvent?->event)->name ?? 'Event';

        return new Envelope(
            subject: 'Withdrawal Confirmation – ' . $eventName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.withdrawal.player',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
