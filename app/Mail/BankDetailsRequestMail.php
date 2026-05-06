<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BankDetailsRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Only store lightweight primitives so the queued job doesn't blow memory
    public int $userId;
    public string $userName;
    public array $registrationIds;
    public int $registrationCount;
    public string $signedUrl;
    public string $emailSubject;

    public function __construct(User $user, Collection $registrations)
    {
        $this->userId            = $user->id;
        $this->userName          = $user->name;
        $this->registrationIds   = $registrations->pluck('id')->toArray();
        $this->registrationCount = $registrations->count();

        // Signed URL scoped to user — valid 7 days, no login required
        $this->signedUrl = URL::temporarySignedRoute(
            'refund.bank-details.show',
            now()->addDays(7),
            ['user' => $user->id]
        );

        $count = $registrations->count();
        if ($count === 1) {
            $eventName = optional($registrations->first()->categoryEvent?->event)->name ?? 'Event';
            $this->emailSubject = 'Action Required: Bank Details for Refund – ' . $eventName;
        } else {
            $this->emailSubject = "Action Required: Bank Details for {$count} Pending Refunds";
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        // Re-query here (in the queue worker) instead of serializing full models
        $registrations = \App\Models\CategoryEventRegistration::with('categoryEvent.event', 'players')
            ->whereIn('id', $this->registrationIds)
            ->get();

        return new Content(
            markdown: 'emails.refund.bank-details-request',
            with: [
                'registrations' => $registrations,
                'signedUrl'     => $this->signedUrl,
                'userName'      => $this->userName,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
