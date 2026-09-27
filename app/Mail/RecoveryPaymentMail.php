<?php

namespace App\Mail;

use App\Models\RegistrationPaymentRecovery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class RecoveryPaymentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public RegistrationPaymentRecovery $recovery) {}

    public function envelope(): Envelope
    {
        $event = $this->recovery->mail_snapshot['event'] ?? 'Cape Tennis event';
        return new Envelope(subject: 'Payment required for '.$event);
    }

    public function content(): Content
    {
        $url = URL::temporarySignedRoute('registration.recovery.show', $this->recovery->link_expires_at, ['recovery' => $this->recovery->id]);
        return new Content(markdown: 'emails.registration.payment-recovery', with: ['paymentUrl' => $url]);
    }

    public function attachments(): array { return []; }
}
