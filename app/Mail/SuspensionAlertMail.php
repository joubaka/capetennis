<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\PlayerSuspension;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuspensionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Player $player,
        public PlayerSuspension $suspension
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Suspension Triggered — ' . $this->player->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.disciplinary.suspension-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
