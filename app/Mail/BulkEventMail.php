<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulkEventMail extends Mailable implements ShouldQueue
{
  use Queueable, SerializesModels;

  public function __construct(
    public string $subjectLine,
    public string $body,
    public string $fromName,
    public string $replyToAddress
  ) {
  }

  public function build()
  {
    return $this
      // ⚠️ FROM address must stay a verified SMTP address
      ->from(config('mail.from.address'), $this->fromName)

      // ✅ Reply-To can be dynamic (event email)
      ->replyTo($this->replyToAddress)

      ->subject($this->subjectLine)
      ->html($this->body);
  }
}
