<?php

namespace App\Mail;

use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalPlayerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CategoryEventRegistration $registration;

    /** 'self' | 'admin' */
    public string $initiatedBy;

    /** Resolved subject after placeholder substitution. */
    private string $resolvedSubject;

    /** Resolved body Markdown after placeholder substitution, or null to use default blade. */
    private ?string $resolvedBody;

    public function __construct(CategoryEventRegistration $registration, string $initiatedBy = 'self')
    {
        $this->registration = $registration;
        $this->initiatedBy  = $initiatedBy;
        $this->resolve();
    }

    private function resolve(): void
    {
        $player       = $this->registration->players->first();
        $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
        $event        = $this->registration->categoryEvent?->event;
        $eventName    = $event?->name ?? 'Event';
        $categoryName = $this->registration->categoryEvent?->category?->name ?? '';
        $withdrawnAt  = $this->registration->withdrawn_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');
        $initiatedBy  = $this->initiatedBy === 'admin' ? 'Event administrator' : 'You';
        $appName      = config('app.name');

        $placeholders = [
            '{player_name}'   => $playerName,
            '{event_name}'    => $eventName,
            '{category_name}' => $categoryName,
            '{withdrawn_at}'  => $withdrawnAt,
            '{initiated_by}'  => $initiatedBy,
            '{app_name}'      => $appName,
        ];

        $storedSubject = SiteSetting::get('player_email_subject_withdrawal');
        $storedBody    = SiteSetting::get('player_email_body_withdrawal');

        $this->resolvedSubject = $storedSubject
            ? str_replace(array_keys($placeholders), array_values($placeholders), $storedSubject)
            : 'Withdrawal Confirmation – ' . $eventName;

        $this->resolvedBody = $storedBody
            ? str_replace(array_keys($placeholders), array_values($placeholders), $storedBody)
            : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolvedSubject);
    }

    public function content(): Content
    {
        if ($this->resolvedBody !== null) {
            return new Content(
                markdown: 'emails.player-notification',
                with: ['body' => $this->resolvedBody],
            );
        }

        return new Content(markdown: 'emails.withdrawal.player');
    }

    public function attachments(): array
    {
        return [];
    }
}
