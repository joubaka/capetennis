<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * BulkEventMail - Used for admin bulk email feature.
 * 
 * IMPORTANT: This mailable does NOT implement ShouldQueue because it is sent
 * from within SendBulkEmailJob, which is already queued. Implementing ShouldQueue
 * here would cause double-queuing and bypass throttling, leading to rate limit errors.
 */
class BulkEventMail extends Mailable
{
  use Queueable, SerializesModels;

  public function __construct(
    public string $subjectLine,
    public string $body,
    public string $fromName,
    public string $replyToAddress,
    public ?string $fromAddress = null
  ) {
  }

  public function build()
  {
    $fromAddress = $this->fromAddress ?? config('mail.from.address');
    app(\App\Services\InvitationMailSecurity::class)->assertAllowedFrom($fromAddress);

    return $this
      // ⚠️ FROM address must stay a verified SMTP address
      ->from($fromAddress, $this->fromName)

      // ✅ Reply-To can be dynamic (event email)
      ->replyTo($this->replyToAddress)

      ->subject($this->subjectLine)
      ->html($this->body);
  }
}

