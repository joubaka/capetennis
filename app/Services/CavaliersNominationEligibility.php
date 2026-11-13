<?php

namespace App\Services;

use App\Models\{CategoryEvent, Event, EventNomination};
use App\Support\EventTypePresentation;
use RuntimeException;

/** Registration admission only; candidate preparation and private ranking stay independent. */
final class CavaliersNominationEligibility
{
    public function applies(Event $event): bool
    {
        return (int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT && EventTypePresentation::template($event) === 'cavaliers_trials';
    }

    public function assertCanRegister(CategoryEvent $division, int $playerId): void
    {
        $event = $division->event;
        if (! $this->applies($event)) return;
        if (! $event->published || ! $event->registrationIsOpen() || ! $division->nominations_published
            || ! EventNomination::where('event_id', $event->id)->where('category_event_id', $division->id)->where('player_id', $playerId)->exists()) {
            throw new RuntimeException('Only a currently published nominee in this Cavaliers division may register while registration is open.');
        }
    }
}
