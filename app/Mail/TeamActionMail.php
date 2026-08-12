<?php

namespace App\Mail;

use App\Models\TeamPaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamActionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamPaymentOrder $order,
        public string $action,
        public array $details = [],
    ) {
    }

    public function envelope(): Envelope
    {
        $event = $this->order->event?->name ?? 'Team event';
        $subjects = [
            'registration' => "Team Registration Confirmed - {$event}",
            'withdrawal' => "Team Player Withdrawn - {$event}",
            'refund_requested' => "Team Refund Requested - {$event}",
            'refund_completed' => "Team Refund Completed - {$event}",
        ];

        return new Envelope(subject: $subjects[$this->action] ?? "Team Registration Update - {$event}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.team.action');
    }
}
