<?php

namespace App\Services;

use App\Models\{DrawAuditLog, Team, TeamFixture, TeamFixturePlayer};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Immutable source membership captured before a competition roster transition. */
class TeamParticipantHistoryService
{
    /** Refresh display names only; source identity and link attestations are never reassigned. */
    public function refreshEventNames(int $eventId, ?int $playerId = null, ?int $importedId = null): void
    {
        DB::transaction(function () use ($eventId, $playerId, $importedId): void {
            app(TeamDrawAdaptationService::class)->lockEvent($eventId);
            TeamFixture::whereHas('draw', fn ($query) => $query->where('event_id', $eventId)->where('locked', false))
                ->where('match_status', 0)->whereDoesntHave('fixtureResults')
                ->when($playerId || $importedId, fn ($query) => $query->whereHas('fixturePlayers', function ($slots) use ($playerId, $importedId) {
                    $slots->where(function ($side) use ($playerId, $importedId) {
                        if ($playerId) $side->where('team1_id', $playerId)->orWhere('team2_id', $playerId);
                        if ($importedId) $side->where('team1_no_profile_id', $importedId)->orWhere('team2_no_profile_id', $importedId);
                    });
                }))->orderBy('id')->chunkById(100, function ($fixtures): void {
                    foreach ($fixtures as $candidate) {
                        $fixture = TeamFixture::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                        if ($this->captureFixture($fixture, refreshNames: true)) {
                            DrawAuditLog::record($fixture->draw_id, 'team_fixture_names_refreshed', null, ['fixture_id' => $fixture->id]);
                        }
                    }
                });
        });
    }

    public function refreshPlayerNames(int $playerId): void
    {
        $eventIds = \App\Models\Draw::whereIn('id', TeamFixture::where('match_status', 0)
            ->whereDoesntHave('fixtureResults')->whereHas('fixturePlayers', fn ($slots) => $slots->where('team1_id', $playerId)->orWhere('team2_id', $playerId))->select('draw_id'))
            ->distinct()->pluck('event_id');
        foreach ($eventIds as $eventId) $this->refreshEventNames((int) $eventId, playerId: $playerId);
    }

    public function assertRevision(\App\Models\TeamFixture $fixture, int $eventId, mixed $expected): void
    {
        $hasHistory = \App\Models\TeamSubstitution::where('event_id', $eventId)->lockForUpdate()->first(['id']) !== null;
        $hasHistory = $hasHistory || \App\Models\DrawAuditLog::where('draw_id', $fixture->draw_id)
            ->whereIn('action', ['team_draw_adapted', 'team_fixture_names_refreshed'])->exists();
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
    public function captureFixture(\App\Models\TeamFixture $fixture, bool $attestNewIdentity = false, bool $refreshNames = false): int
    {
        if ($refreshNames && ($fixture->draw->locked || $fixture->draw->isPlacementDraft()
            || app(\App\Services\Scheduling\UnifiedTeamScheduleService::class)->protected($fixture))) return 0;
        $attestNewIdentity = $attestNewIdentity && $fixture->wasRecentlyCreated
            && (int) $fixture->match_status === 0 && !$fixture->teamResults()->exists();
        $map = $fixture->draw->team_draw_selection['mixed_sides'] ?? [];
        $ids = collect([$fixture->teamTie?->home_team_id, $fixture->teamTie?->away_team_id])->filter()
            ->flatMap(fn ($id) => isset($map[$id]) ? [$map[$id]['boys'], $map[$id]['girls']] : [$id]);
        $teams = Team::with(['category', 'team_players.player', 'team_players_no_profile'])->whereIn('id', $ids)->get()
            ->map(fn ($team) => app(TeamDrawSideResolver::class)->activeRoster($team, (int) $fixture->round_nr, (int) $fixture->id, (int) $fixture->draw_id));
        return $this->capture($fixture->fixturePlayers()->with(['fixture.draw', 'fixture.teamTie'])->get(), $teams, $attestNewIdentity, $refreshNames);
    }
    public function capture(Collection $rows, Collection $teams, bool $attestNewIdentity = false, bool $refreshNames = false): int
    {
        $changed = 0;
        foreach ($rows as $row) {
            $snapshot = $row->participant_snapshot ?? [];
            foreach ([1, 2] as $side) {
                if (isset($snapshot[$side]) && !$refreshNames) continue;
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
                    if (isset($snapshot[$side])) {
                        if ($this->matches($row, $side, $team, (int) $fixture->draw->event_id)) {
                            $snapshot[$side]['name'] = $profile ? $member->player?->full_name : trim($member->name.' '.$member->surname);
                        }
                        break;
                    }
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
                    // Attest only at first capture; existing historical snapshots remain immutable.
                    if ($attestNewIdentity && $imported && (int) $member->player_profile > 0 && (int) $member->rank > 0
                        && $team->team_players_no_profile->where('rank', $member->rank)->count() === 1
                        && $team->team_players_no_profile->where('player_profile', $member->player_profile)->count() === 1
                        && $team->team_players->where('rank', $member->rank)->count() === 1
                        && $team->team_players->where('player_id', $member->player_profile)->count() === 1
                        && $team->team_players->contains(fn ($mirror) => (int) $mirror->rank === (int) $member->rank && (int) $mirror->player_id === (int) $member->player_profile)) {
                        $snapshot[$side]['linked_profile_id'] = (int) $member->player_profile;
                    }
                    break;
                }
            }
            if ($snapshot !== ($row->participant_snapshot ?? [])) {
                $row->forceFill(['participant_snapshot' => $snapshot])->save();
                $changed++;
            }
        }
        return $changed;
    }

    public function matches(TeamFixturePlayer $row, int $side, Team $source, int $eventId): bool
    {
        $snapshot = $row->participant_snapshot[$side] ?? null;
        return $snapshot && isset($snapshot['event_id'], $snapshot['source_team_id'], $snapshot['category_event_id'], $snapshot['region_id'])
            && (int) $snapshot['event_id'] === $eventId
            && (int) $snapshot['source_team_id'] === (int) $source->id
            && (int) $snapshot['category_event_id'] === (int) $source->category_event_id
            && (int) $snapshot['region_id'] === (int) $source->region_id
            && ($snapshot['profile_id'] ?? null) === ($row->{'team'.$side.'_id'} ? (int) $row->{'team'.$side.'_id'} : null)
            && ($snapshot['imported_id'] ?? null) === ($row->{'team'.$side.'_no_profile_id'} ? (int) $row->{'team'.$side.'_no_profile_id'} : null);
    }
}
