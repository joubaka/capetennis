<?php

namespace App\Observers;

use App\Models\Fixture;
use App\Services\InterprovincialTrials\TrialRefreshQueue;

class TrialFixtureObserver
{
    public function saved(Fixture $fixture): void
    {
        if ($fixture->wasChanged(['match_status', 'winner_registration', 'registration1_id', 'registration2_id']) || $fixture->wasRecentlyCreated) {
            if ($eventId = $fixture->draw?->event_id) {
                app(TrialRefreshQueue::class)->remember((int) $eventId, true, (int) $fixture->draw->category_event_id);
            }
        }
    }
    public function deleted(Fixture $fixture): void
    {
        if ($eventId = $fixture->draw?->event_id) {
            app(TrialRefreshQueue::class)->remember((int) $eventId, true, (int) $fixture->draw->category_event_id);
        }
    }
}
