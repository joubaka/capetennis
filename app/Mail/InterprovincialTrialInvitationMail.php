<?php

namespace App\Mail;

use App\Models\InterprovincialTrialInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\URL;

class InterprovincialTrialInvitationMail extends Mailable
{
    use Queueable;
    public function __construct(public InterprovincialTrialInvitation $invitation, public array $messagePayload) {}
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) ($this->messagePayload['from_address'] ?? config('mail.from.address')), (string) ($this->messagePayload['from_name'] ?? config('mail.from.name'))),
            replyTo: [new Address((string) ($this->messagePayload['reply_to'] ?? config('mail.from.address')))],
            subject: (string) $this->messagePayload['subject'],
        );
    }
    public function content(): Content
    {
        $event = $this->invitation->event()->firstOrFail();
        $registrationClosesAt = $event->registrationClosesAt()?->endOfDay();
        $expiresAt = $registrationClosesAt && $registrationClosesAt->isFuture()
            ? $registrationClosesAt
            : now()->addDays(7);

        return new Content(view: 'emails.interprovincial-trials.invitation', with: [
            'invitationUrl' => URL::temporarySignedRoute('interprovincial-trials.invitations.show', $expiresAt, $this->invitation),
            'recipientName' => $this->messagePayload['recipient_name'] ?? '',
            'messageBody' => $this->messagePayload['body'] ?? '',
        ]);
    }
}
