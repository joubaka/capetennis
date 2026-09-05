<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AnnouncementMail - Used for event/tournament announcements.
 * 
 * IMPORTANT: This mailable does NOT implement ShouldQueue because it is sent
 * from within SendBulkEmailJob, which is already queued. Implementing ShouldQueue
 * here would cause double-queuing and bypass throttling, leading to rate limit errors.
 */
class AnnouncementMail extends Mailable
{
  use Queueable, SerializesModels;

  public array $data;

  /**
   * Create a new message instance.
   */
  public function __construct(array $data)
  {
    $this->data = $data;
  }

  /**
   * Get the message envelope.
   */
  public function envelope(): Envelope
  {
    $title = trim((string) ($this->data['title'] ?? '')) ?: 'Announcement';

    return new Envelope(
      subject: $title . ' – ' . ($this->data['event'] ?? '')
    );
  }

  /**
   * Get the message content definition.
   */
  public function content(): Content
  {
    return new Content(
        markdown: 'emails.create_announcement',
        with: [
            'datas' => $this->data,  // Changed from 'data' to 'datas'
        ]
    );
  }

  /**
   * Attachments.
   */
  public function attachments(): array
  {
    return [];
  }
}

