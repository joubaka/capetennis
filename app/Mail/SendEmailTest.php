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
    // ✅ Use the FROM email from the mailer account being used
    // The fromEmail MUST match the SMTP account sending the email
    $fromEmail = $this->data['fromEmail'] ?? config('mail.from.address', 'noreply@capetennis.co.za');
    $fromName = $this->data['fromName'] ?? config('mail.from.name', 'Cape Tennis');
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
