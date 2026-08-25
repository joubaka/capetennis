<?php

namespace App\Mail;

use App\Models\MastersInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MastersAdminUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable;
    public function __construct(public MastersInvitation $invitation, public string $action, public ?MastersInvitation $replacement = null) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Cape Tennis Masters invitation update: '.$this->action); }
    public function content(): Content { return new Content(view: 'emails.masters.admin-update'); }
}
