<?php

namespace App\Mail;

use App\Models\InterprovincialTrialInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InterprovincialTrialInvitationMail extends Mailable
{
    use Queueable;
    public function __construct(public InterprovincialTrialInvitation $invitation, public array $messagePayload) {}
    public function envelope(): Envelope { return new Envelope(subject: (string) $this->messagePayload['subject']); }
    public function content(): Content
    {
        return new Content(view: 'emails.interprovincial-trials.invitation', with: [
            'invitationUrl' => route('events.show', [
                'event' => $this->invitation->event_id,
                'player' => $this->invitation->player_id,
                'nomination' => $this->invitation->nomination_id,
            ]).'#trial-nomination-'.$this->invitation->nomination_id,
            'recipientName' => $this->messagePayload['recipient_name'] ?? '',
            'messageBody' => $this->messagePayload['body'] ?? '',
        ]);
    }
}
