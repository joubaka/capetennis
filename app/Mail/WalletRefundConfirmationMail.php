<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Sent to the player immediately after an instant wallet refund is credited.
 */
class WalletRefundConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CategoryEventRegistration $registration;

    public function __construct(
        CategoryEventRegistration $registration,
        public ?string $refundReason = null,
        public ?string $payerName = null,
        public array $adminCc = [],
        public array $adminReplyTo = [],
        public array $emailSnapshot = [],
    )
    {
        $this->registration = $registration;
    }

    public function envelope(): Envelope
    {
        $eventName = $this->emailSnapshot['event_name'] ?? optional($this->registration->categoryEvent?->event)->name ?? 'Event';

        return new Envelope(
            cc: $this->adminCc,
            replyTo: $this->adminReplyTo,
            subject: 'Wallet Refund Confirmed – ' . $eventName
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.refund.wallet-confirmation');
    }

    public function attachments(): array
    {
        return [];
    }

    public function middleware(): array
    {
        return [new RateLimited('outbound-mail')];
    }
}
