<?php

namespace App\Listeners;

use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\InterprovincialTrialInvitation;
use App\Models\RegistrationOrder;
use App\Models\RegistrationPaymentRecovery;
use App\Models\TeamPaymentOrder;
use App\Models\TeamSelectionInvitation;
use App\Services\EventCommunicationService;
use Illuminate\Mail\Events\MessageSending;

/** Hold event transactional messages for recipient/content approval before transport. */
class RequireEventEmailReview
{
    public function __construct(private EventCommunicationService $communications) {}

    public function handle(MessageSending $mail): ?bool
    {
        if (($mail->data['event_mail_reviewed'] ?? false) === true) return null;

        $events = $this->events($mail->data);
        $scoped = $events->filter(fn (Event $event) => $event->isTeam() || $event->isInterprovincialTrials());
        if ($scoped->isEmpty()) return null;
        // A mixed-event refund/confirmation must never leak another event's details into a manager's draft.
        if ($events->count() !== 1) {
            \Illuminate\Support\Facades\Log::warning('Mixed-event email held: separate messages are required for manual review.', [
                'event_ids' => $events->pluck('id')->all(),
            ]);

            app(\App\Services\OutboundMailHistory::class)->held($mail);
            return false;
        }
        $event = $scoped->first();
        $subject = (string) $mail->message->getSubject();
        $html = $mail->message->getHtmlBody() ?? nl2br(e($mail->message->getTextBody() ?? ''));
        $recipients = collect([...$mail->message->getTo(), ...$mail->message->getCc(), ...$mail->message->getBcc()])
            ->unique(fn ($address) => mb_strtolower($address->getAddress()))
            ->map(fn ($address) => ['email' => $address->getAddress(), 'name' => $address->getName(), 'kind' => 'transaction', 'subject' => $subject, 'html' => $html])->values()->all();
        $source = 'transaction-mail:'.$event->id.':'.hash('sha256', json_encode([$subject, $recipients], JSON_THROW_ON_ERROR));
        $this->communications->draftFixed($event, $source, $recipients, $subject);

        app(\App\Services\OutboundMailHistory::class)->held($mail);
        return false;
    }

    private function events(array $data): \Illuminate\Support\Collection
    {
        $events = collect();
        foreach ($data as $value) {
            if ($value instanceof Event) $events->push($value);
            elseif ($value instanceof TeamPaymentOrder) $events->push($value->event);
            elseif ($value instanceof RegistrationOrder) {
                foreach ($value->items as $item) $events->push($item->category_event?->event);
            } elseif ($value instanceof CategoryEventRegistration) $events->push($value->categoryEvent?->event);
            elseif ($value instanceof TeamSelectionInvitation || $value instanceof InterprovincialTrialInvitation) $events->push($value->event);
            elseif ($value instanceof RegistrationPaymentRecovery) {
                if ($value->order) $events = $events->concat($this->events(['order' => $value->order]));
            } elseif ($value instanceof \Illuminate\Support\Collection || is_array($value)) {
                $events = $events->concat($this->events($value instanceof \Illuminate\Support\Collection ? $value->all() : $value));
            }
        }

        return $events->filter(fn ($event) => $event instanceof Event)->unique('id')->values();
    }
}
