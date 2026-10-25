<?php

namespace App\Mail;

use App\Models\TeamSelectionInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class TeamSelectionInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public TeamSelectionInvitation $invitation,
        public string $kind = 'invitation',
        public array $campaign = [],
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->campaign['subject'] ?? $this->invitation->selectionImport?->email_subject;
        if ($this->kind === 'replacement' && $subject) {
            $subject = 'Replacement: '.$subject;
        }

        return new Envelope(
            from: filled($this->campaign['from_name'] ?? null)
                ? new Address(config('mail.from.address'), $this->campaign['from_name'])
                : null,
            subject: $subject ?: ($this->kind === 'replacement'
                ? 'Platteland team replacement invitation'
                : 'Platteland team invitation'),
            replyTo: filled($this->campaign['reply_to'] ?? null)
                ? [new Address($this->campaign['reply_to'])]
                : [],
        );
    }

    public function content(): Content
    {
        if (! empty($this->campaign['notification_only'])) {
            $event = $this->invitation->selectionImport?->event;
            return new Content(view: 'emails.player-contact-update', with: [
                'playerName' => $this->invitation->player?->full_name ?? 'Player',
                'messageBody' => (string) ($this->campaign['message'] ?? $this->invitation->selectionImport?->email_message ?? 'Your team selection has been updated.'),
                'eventName' => $event?->name ?? 'Cape Tennis',
                'eventUrl' => $this->campaign['event']['public_url'] ?? ($event?->published ? route('events.show', $event) : null),
            ]);
        }
        return new Content(view: 'emails.team-selection.invitation');
    }
}
