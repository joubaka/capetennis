<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyWithdrawalSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public Collection $withdrawals,
        public Carbon $periodStart,
        public Carbon $periodEnd,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Withdrawal Summary - '.$this->event->name.' - '.$this->periodStart->format('d M Y'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.withdrawal.daily-summary');
    }

    public function attachments(): array
    {
        return [];
    }
}
