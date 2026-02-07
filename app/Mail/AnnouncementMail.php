<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable implements ShouldQueue
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
    return new Envelope(
      subject: 'Announcement – ' . ($this->data['event'] ?? '')
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
        'data' => $this->data,
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
