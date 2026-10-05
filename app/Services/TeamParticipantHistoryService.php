<?php

namespace App\Services;

use App\Models\{Team, TeamFixturePlayer};
use Illuminate\Support\Collection;

/** Immutable source membership captured before a competition roster transition. */
class TeamParticipantHistoryService
{
    public function assertRevision(\App\Models\TeamFixture $fixture, int $eventId, mixed $expected): void
    {
        $hasHistory = \App\Models\TeamSubstitution::where('event_id', $eventId)->lockForUpdate()->first(['id']) !== null;
        if ($expected === null && !$hasHistory) return;
        abort_unless(is_string($expected) && hash_equals($this->revision($fixture, true), $expected), 409,
            'Fixture participants changed or this page is outdated. Reload the fixture before scoring or starting play.');
    }
    public function revision(\App\Models\TeamFixture $fixture, bool $lock = false): string
    {
        $rows = !$lock && $fixture->relationLoaded('fixturePlayers') ? $fixture->fixturePlayers
            : $fixture->fixturePlayers()->when($lock, fn ($query) => $query->lockForUpdate())->get();
        return hash('sha256', json_encode($rows->sortBy('id')->map(fn ($row) => [
            $row->id, $row->slot_no, $row->team1_id, $row->team2_id,
            $row->team1_no_profile_id, $row->team2_no_profile_id, $row->participant_snapshot,
        ])->values()->all()));
    }
    public function captureFixture(\App\Models\TeamFixture $fixture): void
    {
        $map = $fixture->draw->team_draw_selection['mixed_sides'] ?? [];
        $ids = collect([$fixture->teamTie?->home_team_id, $fixture->teamTie?->away_team_id])->filter()
            ->flatMap(fn ($id) => isset($map[$id]) ? [$map[$id]['boys'], $map[$id]['girls']] : [$id]);
        $teams = Team::with(['category', 'team_players.player', 'team_players_no_profile'])->whereIn('id', $ids)->get()
            ->map(fn ($team) => app(TeamDrawSideResolver::class)->activeRoster($team, (int) $fixture->round_nr, (int) $fixture->id, (int) $fixture->draw_id));
        $this->capture($fixture->fixturePlayers()->with(['fixture.draw', 'fixture.teamTie'])->get(), $teams);
    }
    public function capture(Collection $rows, Collection $teams): void
    {
        foreach ($rows as $row) {
            $snapshot = $row->participant_snapshot ?? [];
            foreach ([1, 2] as $side) {
                if (isset($snapshot[$side])) continue;
                $fixture = $row->fixture;
                $representative = $fixture->teamTie?->{($side === 1 ? 'home' : 'away').'_team_id'};
                $entry = $fixture->draw?->team_draw_selection['mixed_sides'][$representative] ?? null;
                $ids = $entry ? [$entry['boys'], $entry['girls']] : [$representative];
                foreach ($teams->whereIn('id', $ids) as $team) {
                    if ((int) $team->category?->event_id !== (int) $fixture->draw?->event_id) continue;
                    $profile = $row->{'team'.$side.'_id'};
                    $imported = $row->{'team'.$side.'_no_profile_id'};
                    $member = $profile && !$imported ? $team->team_players->firstWhere('player_id', $profile)
                        : ($imported && !$profile ? $team->team_players_no_profile->firstWhere('id', $imported) : null);
                    if (!$member) continue;
                    $snapshot[$side] = [
                        'event_id' => (int) $fixture->draw->event_id,
                        'source_team_id' => (int) $team->id,
                        'category_event_id' => (int) $team->category_event_id,
                        'region_id' => (int) $team->region_id,
                        'profile_id' => $profile ? (int) $profile : null,
                        'imported_id' => $imported ? (int) $imported : null,
                        'rank' => (int) $member->rank,
                        'anchor_type' => $member->competition_anchor_type ?? ($profile ? 'profile' : 'imported'),
                        'anchor_id' => (int) ($member->competition_anchor_id ?? $member->id),
                        'name' => $profile ? $member->player?->full_name : trim($member->name.' '.$member->surname),
                    ];
                    break;
                }
            }
            if ($snapshot !== ($row->participant_snapshot ?? [])) $row->forceFill(['participant_snapshot' => $snapshot])->save();
        }
    }

    public function matches(TeamFixturePlayer $row, int $side, Team $source, int $eventId): bool
    {
        $snapshot = $row->participant_snapshot[$side] ?? null;
        return $snapshot && (int) $snapshot['event_id'] === $eventId
            && (int) $snapshot['source_team_id'] === (int) $source->id
            && (int) $snapshot['category_event_id'] === (int) $source->category_event_id
            && (int) $snapshot['region_id'] === (int) $source->region_id
            && ($snapshot['profile_id'] ?? null) === ($row->{'team'.$side.'_id'} ? (int) $row->{'team'.$side.'_id'} : null)
            && ($snapshot['imported_id'] ?? null) === ($row->{'team'.$side.'_no_profile_id'} ? (int) $row->{'team'.$side.'_no_profile_id'} : null);
    }
}
