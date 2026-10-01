<?php

namespace App\Services\InterprovincialTrials;

use App\Models\EventNomination;
use App\Models\InterprovincialTrialInvitation;
use App\Models\User;
use App\Services\PlayerIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NominationProfileService
{
    public function authorize(EventNomination $nomination, User $user): void
    {
        $nomination->loadMissing(['event.eventTypeModel', 'categoryEvent']);
        abort_unless($nomination->event?->isInterprovincialTrials()
            && (int) $nomination->categoryEvent?->event_id === (int) $nomination->event_id
            && $nomination->player_id === null, 404);
        if (! $nomination->categoryEvent->nominations_published) {
            $invitation = InterprovincialTrialInvitation::where('event_id', $nomination->event_id)
                ->where('category_event_id', $nomination->category_event_id)->where('nomination_id', $nomination->id)
                ->where('status', '!=', 'prepared')
                ->latest('id')->first();
            abort_unless($invitation && $invitation->player_id === null
                && in_array($invitation->status, ['queued', 'sent'], true), 404);
        }
        $event = $nomination->event;
        if (! $event->published || ! $event->hasOpenRegistrationLifecycle() || (int) $event->signUp !== 1
            || ($event->registrationClosesAt() && now()->gt($event->registrationClosesAt()->endOfDay()))) {
            throw ValidationException::withMessages(['nomination' => 'Registration for this trial is closed.']);
        }
    }

    public function complete(int $nominationId, User $user, array $details): array
    {
        return DB::transaction(function () use ($nominationId, $user, $details): array {
            $nomination = EventNomination::findOrFail($nominationId);
            \App\Models\Event::whereKey($nomination->event_id)->lockForUpdate()->firstOrFail();
            \App\Models\CategoryEvent::whereKey($nomination->category_event_id)->lockForUpdate()->firstOrFail();
            $nomination = EventNomination::whereKey($nominationId)->lockForUpdate()->firstOrFail();
            $this->authorize($nomination, $user);
            $identity = app(PlayerIdentityService::class);
            $result = $identity->findOrCreate(array_merge($details, [
                'name' => $nomination->nominee_name, 'surname' => $nomination->nominee_surname, 'userId' => null,
            ]));
            if ($result['created']) {
                $result['player']->markProfileUpdated();
            }
            if (EventNomination::where('category_event_id', $nomination->category_event_id)
                ->where('player_id', $result['player']->id)->whereKeyNot($nomination->id)->exists()) {
                throw ValidationException::withMessages(['nomination' => 'This player is already nominated in this category. Ask the event administrator to remove the duplicate nomination.']);
            }
            $nomination->update(['player_id' => $result['player']->id]);
            InterprovincialTrialInvitation::where('event_id', $nomination->event_id)
                ->where('category_event_id', $nomination->category_event_id)->where('nomination_id', $nomination->id)
                ->whereNull('player_id')->update(['player_id' => $result['player']->id]);

            return $result + ['nomination' => $nomination];
        });
    }
}
