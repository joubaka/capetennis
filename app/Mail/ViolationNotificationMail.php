<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\PlayerViolation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ViolationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Player $player,
        public PlayerViolation $violation,
        public ?User $recorder = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Disciplinary Violation Recorded — ' . $this->player->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.disciplinary.violation-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
