<?php

namespace App\Mail;

use App\Models\MastersInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MastersInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public MastersInvitation $invitation, public string $kind = 'invitation') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function subjectLine(): string
    {
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
        return new Content(view: 'emails.masters.invitation');
    }
}
