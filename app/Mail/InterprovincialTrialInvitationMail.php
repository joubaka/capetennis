<?php

namespace App\Mail;

use App\Models\InterprovincialTrialInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class InterprovincialTrialInvitationMail extends Mailable
{
    use Queueable;
    public function __construct(public InterprovincialTrialInvitation $invitation) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Invitation to Interprovincial Trials'); }
    public function content(): Content
    {
        return new Content(view: 'emails.interprovincial-trials.invitation', with: [
            'invitationUrl' => URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addDays(7), ['invitation' => $this->invitation]),
        ]);
    }
}
