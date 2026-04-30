<?php

namespace App\Mail;

use App\Models\RegistrationOrder;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public RegistrationOrder $order;

    /** Resolved subject (after placeholder substitution). */
    private string $resolvedSubject;

    /** Resolved body Markdown (after placeholder substitution), or null to use default blade. */
    private ?string $resolvedBody;

    public function __construct(RegistrationOrder $order)
    {
        $this->order = $order;
        $this->resolve();
    }

    private function resolve(): void
    {
        $firstItem  = $this->order->items->first();
        $eventName  = optional($firstItem?->category_event?->event)->name ?? 'Event';
        $userName   = $this->order->user?->name ?? 'Player';
        $appName    = config('app.name');

        $placeholders = [
            '{event_name}' => $eventName,
            '{user_name}'  => $userName,
            '{app_name}'   => $appName,
        ];

        $storedSubject = SiteSetting::get('player_email_subject_registration');
        $storedBody    = SiteSetting::get('player_email_body_registration');

        $this->resolvedSubject = $storedSubject
            ? str_replace(array_keys($placeholders), array_values($placeholders), $storedSubject)
            : 'Registration Confirmation – ' . $eventName;

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

        return new Content(markdown: 'emails.registration.confirmation');
    }

    public function attachments(): array
    {
        return [];
    }
}
