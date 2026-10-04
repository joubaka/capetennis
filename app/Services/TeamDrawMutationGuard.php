<?php

namespace App\Services;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\Draw;
use App\Models\TeamFixture;
use App\Services\Draw\DrawMutationPolicy;

final class TeamDrawMutationGuard
{
    public function schedule(Draw $draw): void
    {
        // State checks are independent of Gate::before's super-user shortcut.
        abort_unless(DrawMutationPolicy::for($draw)->canModifySchedule(), 409, 'The draw is locked.');
    }

    public function fixture(TeamFixture $fixture): void
    {
        $this->schedule($fixture->draw);
        abort_if($fixture->fixtureResults()->exists()
            || (int) $fixture->match_status !== FixtureState::STATUS_PENDING
            || $fixture->teamTie?->isCompleted(), 409, 'A fixture with play or results cannot be changed structurally.');
    }

    public function destructive(Draw $draw): void
    {
        $this->schedule($draw);
        abort_if($draw->published, 409, 'Unpublish the draw before replacing or clearing fixtures.');
        abort_if($draw->teamTies()->locked()->exists(), 409, 'Published or completed ties cannot be replaced or cleared.');
        abort_if(TeamFixture::where('draw_id', $draw->id)
            ->where(fn ($q) => $q->whereHas('fixtureResults')
                ->orWhere('match_status', '!=', FixtureState::STATUS_PENDING))->exists(),
            409, 'A draw with play or recorded results cannot be replaced or cleared.');
    }
}
