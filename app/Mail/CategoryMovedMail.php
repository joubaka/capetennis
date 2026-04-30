<?php

namespace App\Mail;

use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CategoryMovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $data;

    /** Resolved subject after placeholder substitution. */
    private string $resolvedSubject;

    /** Resolved body Markdown after placeholder substitution, or null to use default blade. */
    private ?string $resolvedBody;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->resolve();
    }

    private function resolve(): void
    {
        $placeholders = [
            '{player_name}'  => $this->data['player_name']  ?? '',
            '{event_name}'   => $this->data['event_name']   ?? 'Event',
            '{old_category}' => $this->data['old_category'] ?? '',
            '{new_category}' => $this->data['new_category'] ?? '',
            '{changed_by}'   => $this->data['changed_by']   ?? '',
            '{app_name}'     => config('app.name'),
        ];

        $storedSubject = SiteSetting::get('player_email_subject_move');
        $storedBody    = SiteSetting::get('player_email_body_move');

        $this->resolvedSubject = $storedSubject
            ? str_replace(array_keys($placeholders), array_values($placeholders), $storedSubject)
            : 'Category Changed – ' . ($this->data['event_name'] ?? 'Event');

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

        return new Content(
            markdown: 'emails.category-moved',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
