<?php

namespace App\Mail;

use App\Models\MastersInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

// The Masters sending job owns queueing and rate limiting. This also prevents
// older SendBulkEmailJob payloads from queueing the mailable a second time.
class MastersInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(public MastersInvitation $invitation, public string $kind = 'invitation', public array $messagePayload = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: isset($this->messagePayload['from_address']) ? new Address($this->messagePayload['from_address'], $this->messagePayload['from_name'] ?? '') : null,
            replyTo: isset($this->messagePayload['reply_to']) ? [new Address($this->messagePayload['reply_to'])] : [],
            subject: $this->subjectLine(),
        );
    }

    public function subjectLine(): string
    {
        if ($this->kind === 'invitation' && filled($this->messagePayload['subject'] ?? null)) return (string) $this->messagePayload['subject'];
        return match ($this->kind) {
            'replacement' => 'Cape Tennis Masters replacement invitation',
            'confirmed' => 'Cape Tennis Masters payment confirmed',
            'declined' => 'Cape Tennis Masters invitation declined',
            'withdrawn' => 'Cape Tennis Masters withdrawal recorded',
            default => 'Cape Tennis Masters invitation',
        };
    }

    public function content(): Content
    {
        if (! empty($this->messagePayload['notification_only'])) {
            $event = $this->invitation->batch?->event;
            return new Content(view: 'emails.player-contact-update', with: [
                'playerName' => $this->invitation->player?->full_name ?? 'Player',
                'messageBody' => (string) ($this->messagePayload['body'] ?? match ($this->kind) {
                    'confirmed' => 'Your place in this event is confirmed.',
                    'declined' => 'Your unavailability has been recorded.',
                    'withdrawn' => 'Your withdrawal has been recorded.',
                    default => 'You have been invited to this event.',
                }),
                'eventName' => $event?->name ?? 'Cape Tennis',
                'eventUrl' => $event?->published ? route('events.show', $event) : null,
            ]);
        }
        return new Content(view: 'emails.masters.invitation');
    }
}
