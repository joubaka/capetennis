<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class HostEventApplicationMail extends Mailable
{
    public function __construct(public int $applicationId) {}

    public function build(): static
    {
        return $this->subject('New CTR event hosting proposal')->view('emails.host-event-application');
    }
}
