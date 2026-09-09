<?php

namespace App\Mail;

use App\Models\TeamSelectionInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TeamSelectionInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(public TeamSelectionInvitation $invitation, public string $kind = 'invitation') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'replacement'
            ? 'Platteland team replacement invitation'
            : 'Platteland 2026 team invitation');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-selection.invitation');
    }
}
