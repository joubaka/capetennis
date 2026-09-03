<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;

/** Produces operator-facing readiness checks without changing draw state. */
final class DrawReadinessService
{
    public function for(Draw $draw): array
    {
        $draw->loadMissing(['drawFixtures', 'registrations', 'venues', 'teams_in_draw', 'event']);
        $fixtures = $draw->drawFixtures;
        $isTeamDraw = (bool) ($draw->event?->isTeam() || $draw->team_event_format_id);
        $groupParticipants = \App\Models\DrawGroupRegistration::whereIn('draw_group_id', $draw->groups()->select('id'))->distinct()->count('registration_id');
        $participantCount = $groupParticipants ?: ($isTeamDraw ? $draw->teams_in_draw->count() : $draw->registrations->count());
        $scored = $fixtures->filter(fn ($fixture) => $fixture->relationLoaded('fixtureResults')
            ? $fixture->fixtureResults->isNotEmpty()
            : $fixture->fixtureResults()->exists())->count();

        $checks = [
            'fixtures' => ['ok' => $fixtures->isNotEmpty(), 'label' => $fixtures->isNotEmpty() ? 'Fixtures generated' : 'Fixtures still need to be generated'],
            'participants' => ['ok' => $participantCount > 0, 'label' => $participantCount > 0 ? $participantCount . ' participants assigned' : 'No participants assigned'],
            'venues' => ['ok' => $draw->venues->isNotEmpty(), 'label' => $draw->venues->isNotEmpty() ? 'Venues available' : 'Assign at least one venue before scheduling'],
            'scores' => ['ok' => true, 'label' => $scored . ' fixture' . ($scored === 1 ? '' : 's') . ' scored'],
        ];

        return [
            'ready_to_publish' => $checks['fixtures']['ok'] && $checks['participants']['ok'],
            'checks' => $checks,
            'fixture_count' => $fixtures->count(),
            'scored_count' => $scored,
        ];
    }
}
