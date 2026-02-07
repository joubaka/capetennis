<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendEmailTest extends Mailable
{
  use Queueable, SerializesModels;

  public $data;

  public function __construct($data)
  {
    $this->data = $data;
  }

  /**
   * Build the envelope.
   */
  public function envelope(): Envelope
  {
    // 🔹 Default values
    $defaultFromEmail = 'capetennis@capetennis.co.za';
    $defaultFromName = 'Cape Tennis';

    // 🔹 Use custom from if provided and valid
    $fromEmail = 'capetennis@capetennis.co.za';

    $fromName = $this->data['fromName'] ?? $defaultFromName;
    $subject = $this->data['subject'] ?? '(no subject)';

    // 🔹 Add reply-to only if valid
    $replyTo = [];
    if (!empty($this->data['replyTo']) && filter_var($this->data['replyTo'], FILTER_VALIDATE_EMAIL)) {
      $replyTo[] = new Address($this->data['replyTo']);
    }

    return new Envelope(
      from: new Address($fromEmail, $fromName),
      subject: $subject,
      replyTo: $replyTo
    );
  }

  /**
   * Define the message body content.
   */
  public function content(): Content
  {
    return new Content(
      view: 'emails.test',
    );
  }

  public function attachments(): array
  {
    return [];
  }
}
