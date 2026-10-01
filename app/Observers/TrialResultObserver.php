<?php

namespace App\Observers;

use App\Models\FixtureResult;
use App\Services\InterprovincialTrials\TrialRefreshQueue;

class TrialResultObserver
{
    public function saved(FixtureResult $result): void { $this->remember($result); }
    public function deleted(FixtureResult $result): void { $this->remember($result); }
    private function remember(FixtureResult $result): void
    {
        if ($eventId = $result->fixtures?->draw?->event_id) {
            app(TrialRefreshQueue::class)->remember((int) $eventId, true, (int) $result->fixtures->draw->category_event_id);
        }
    }
}
