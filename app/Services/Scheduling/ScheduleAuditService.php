<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Event, Fixture, TeamFixture, Venue};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only validation of saved bookings. Findings never change schedule/publication state. */
final class ScheduleAuditService
{
    public function audit(Event $event, array $scope = []): array
    {
        $draws = $event->draws()->get()->keyBy('id');
        $totalFixtures = Fixture::whereIn('draw_id', $draws->keys())->count() + TeamFixture::whereIn('draw_id', $draws->keys())->count();
        if ($totalFixtures > 20000) return ['scope' => $scope, 'checked' => 0, 'compared_event_matches' => 0, 'external_bookings' => 0, 'errors' => 0, 'warnings' => 1, 'unscheduled' => 0, 'rest_minutes' => 0, 'court_gap_minutes' => 0, 'issues' => [], 'omitted_issues' => 0, 'incomplete' => true, 'coverage' => 'Audit inconclusive: this event exceeds the 20,000-fixture safety limit. No bookings were checked.'];
        $individual = Fixture::withoutEagerLoads()->with(['orderOfPlay', 'registration1.players', 'registration2.players'])
            ->whereIn('draw_id', $draws->keys())->withCount('fixtureResults')->get();
        $team = TeamFixture::withoutEagerLoads()->with(['teamTie', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2'])
            ->whereIn('draw_id', $draws->keys())->withCount('fixtureResults')->get();
        $draft = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $rest = max(0, (int) ($draft['player_rest'] ?? 60));
        $gap = max(0, (int) ($draft['court_gap'] ?? 0));
        $rows = [];
        $unscheduled = 0;
        foreach ($individual->concat($team) as $fixture) {
            $isTeam = $fixture instanceof TeamFixture;
            $slot = $isTeam ? $fixture : $fixture->orderOfPlay;
            $time = $isTeam ? $fixture->scheduled_at : $slot?->time;
            if (! $time) {
                // Undated matches have no selected day/venue. Count across the scoped draw, explicitly.
                if ((int) $fixture->match_status === 0 && ! $fixture->fixture_results_count && (empty($scope['draw_id']) || (int) $fixture->draw_id === (int) $scope['draw_id'])
                    && ($isTeam || ($fixture->registration1_id && $fixture->registration2_id) || $fixture->registration1_source_group_id || $fixture->registration2_source_group_id)) $unscheduled++;
                continue;
            }
            $row = $this->row($fixture, $draws[$fixture->draw_id]->drawName, $isTeam, true);
            $rows[$row['key']] = $row;
        }
        $venueNames = Venue::whereIn('id', array_unique(array_column($rows, 'venue')))->pluck('name', 'id');
        foreach ($rows as &$row) $row['venue_name'] = $venueNames[$row['venue']] ?? 'Venue unassigned';
        unset($row);
        $issues = [];
        $counts = ['errors' => 0, 'warnings' => 0];
        $seen = [];
        $add = function (string $severity, string $code, string $message, array $bookings) use (&$issues, &$counts, &$seen, $scope, $event) {
            $own = array_values(array_filter($bookings, fn ($row) => $row['own']));
            if (! array_filter($own, fn ($row) => $this->inScope($row, $scope))) return;
            $keys = array_column($bookings, 'key'); sort($keys);
            $identity = $code.'|'.implode('|', $keys);
            if (isset($seen[$identity])) return;
            $seen[$identity] = true;
            $counts[$severity === 'error' ? 'errors' : 'warnings']++;
            if (count($issues) >= 200) return;
            $issues[] = ['severity' => $severity, 'code' => $code, 'message' => $message, 'matches' => array_map(fn ($row) => [
                'label' => $row['label'], 'time' => $row['start']->format('Y-m-d H:i').'–'.$row['end']->format('H:i').' · '.$row['venue_name'].' / Court '.$row['court'],
                'url' => route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $row['start']->toDateString()]).'#match-'.str_replace(':', '-', $row['key']),
            ], $own), 'external' => count($own) !== count($bookings)];
        };
        foreach ($rows as $row) {
            if (! $row['venue'] || ! trim($row['court'])) $add('error', 'missing_location', 'A saved match is missing its venue or court.', [$row]);
            if ($row['raw_duration'] < 1) $add('warning', 'missing_duration', 'Match duration is missing or invalid; overlap checks use the scheduling default.', [$row]);
            if (count($row['players']) < $row['expected_players']) $add('warning', 'unknown_players', 'Player identities are unresolved; player overlap checks cannot fully verify this match.', [$row]);
            $programmeRound = collect($draft['programme']['rounds'] ?? [])->first(fn ($round) => (int) ($round['draw_id'] ?? $round['drawId'] ?? 0) === $row['draw'] && (int) $round['round'] === $row['round']);
            $day = $programmeRound ? ($draft['programme']['days'][$programmeRound['day'] - 1] ?? null) : null;
            if ($day && ($row['start']->lt(Carbon::parse($day['start'])) || $row['end']->gt(Carbon::parse($day['end'])))) $add('error', 'outside_programme', 'Saved time falls outside its configured programme day.', [$row]);
        }
        $externalCount = 0;
        $incomplete = false;
        if ($rows) {
            $longest = max(120, (int) DB::table('order_of_plays')->whereNotNull('time')->max('duration_minutes'), (int) DB::table('team_fixtures')->whereNotNull('scheduled_at')->max('duration_min'));
            $start = collect($rows)->min(fn ($row) => $row['start'])->copy()->subMinutes($longest + $rest + $gap);
            $end = collect($rows)->max(fn ($row) => $row['end'])->copy()->addMinutes($rest + $gap);
            $venues = array_unique(array_filter(array_column($rows, 'venue')));
            $players = array_unique(array_merge(...array_column($rows, 'players')));
            $noProfiles = collect($players)->filter(fn ($id) => str_starts_with($id, 'no-profile:'))->map(fn ($id) => (int) substr($id, 11))->all();
            $profiles = collect($players)->filter(fn ($id) => str_starts_with($id, 'profile:'))->map(fn ($id) => (int) substr($id, 8))->all();
            $foreignIndividual = Fixture::withoutEagerLoads()->with(['orderOfPlay', 'registration1.players', 'registration2.players'])
                ->whereHas('draw', fn ($query) => $query->where('event_id', '!=', $event->id))
                ->whereHas('orderOfPlay', fn ($query) => $query->whereBetween('time', [$start, $end]))
                ->where(fn ($query) => $query->whereHas('orderOfPlay', fn ($q) => $q->whereIn('venue_id', $venues))
                    ->orWhereHas('registration1.players', fn ($q) => $q->whereIn('players.id', $profiles))
                    ->orWhereHas('registration2.players', fn ($q) => $q->whereIn('players.id', $profiles)))->limit(10001)->get();
            $foreignTeam = TeamFixture::withoutEagerLoads()->with(['teamTie', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2'])
                ->whereHas('draw', fn ($query) => $query->where('event_id', '!=', $event->id))->whereBetween('scheduled_at', [$start, $end])
                ->where(fn ($query) => $query->whereIn('venue_id', $venues)->orWhereHas('fixturePlayers', fn ($q) => $q->whereIn('team1_id', $profiles)->orWhereIn('team2_id', $profiles)->orWhereIn('team1_no_profile_id', $noProfiles)->orWhereIn('team2_no_profile_id', $noProfiles)
                    ->orWhereHas('noProfile1', fn ($linked) => $linked->whereIn('player_profile', $profiles))->orWhereHas('noProfile2', fn ($linked) => $linked->whereIn('player_profile', $profiles))))->limit(10001)->get();
            if ($foreignIndividual->count() > 10000 || $foreignTeam->count() > 10000) {
                $incomplete = true;
                $add('warning', 'coverage_limit', 'Outside-event footprint exceeds the audit limit; external conflict checking is incomplete.', [reset($rows)]);
            }
            foreach ($foreignIndividual->concat($foreignTeam) as $fixture) {
                $row = $this->row($fixture, '', $fixture instanceof TeamFixture, false);
                $rows[$row['key']] = $row; $externalCount++;
            }
        }
        $courts = []; $players = [];
        foreach ($rows as $row) {
            if ($row['venue'] && trim($row['court'])) $courts[$row['venue'].'|'.ScheduleAvailability::courtKey($row['court'])][] = $row;
            foreach ($row['players'] as $player) $players[$player][] = $row;
        }
        $comparisons = 0;
        foreach ($courts as $bookings) {
            if (! $this->pairs($bookings, $gap, $comparisons, function ($before, $after, $minutes) use ($add, $gap) {
            if ($minutes < 0) $add('error', 'court_overlap', 'Two saved bookings overlap on the same physical court.', [$before, $after]);
            elseif ($minutes < $gap) $add('warning', 'court_gap', 'The saved court turnaround gap is shorter than '.$gap.' minutes.', [$before, $after]);
            })) { $incomplete = true; break; }
        }
        foreach ($players as $bookings) {
            if (! $this->pairs($bookings, $rest, $comparisons, function ($before, $after, $minutes) use ($add, $rest) {
            if ($minutes < 0) $add('error', 'player_overlap', 'A player has overlapping saved matches.', [$before, $after]);
            elseif ($minutes < $rest) $add('warning', 'player_rest', 'A player has only '.$minutes.' minutes between matches; configured rest is '.$rest.' minutes.', [$before, $after]);
            })) { $incomplete = true; break; }
        }
        if ($comparisons > 250000) $add('warning', 'comparison_limit', 'The safety limit for conflict comparisons was reached; the audit is inconclusive.', [reset($rows)]);
        foreach ($players as $bookings) {
            usort($bookings, fn ($left, $right) => $left['start'] <=> $right['start']);
            foreach ($bookings as $index => $after) {
                if (! $index) continue;
                $before = $bookings[$index - 1];
                if ($before['venue'] && $after['venue'] && $before['venue'] !== $after['venue'] && $before['start']->isSameDay($after['start'])) {
                    $minutes = (int) $before['end']->diffInMinutes($after['start'], false);
                    $add('warning', 'venue_change', 'A player changes venue with '.$minutes.' minutes between matches. Review travel time.', [$before, $after]);
                }
            }
        }
        $individualById = $individual->keyBy('id');
        foreach ($individual as $feeder) foreach (['parent_fixture_id', 'loser_parent_fixture_id'] as $link) {
            $later = $individualById[$feeder->$link] ?? null;
            if (! $later || (int) $later->draw_id !== (int) $feeder->draw_id) continue;
            $before = $rows['individual:'.$feeder->id] ?? null; $after = $rows['individual:'.$later->id] ?? null;
            if ($before && $after && $before['end']->copy()->addMinutes($rest)->gt($after['start'])) $add('error', 'feeder_order', 'A later match starts before its qualifying match and required player rest finish.', [$before, $after]);
        }
        foreach (collect($rows)->filter(fn ($row) => $row['own'] && str_starts_with($row['key'], 'team:'))->groupBy('draw') as $drawRows) {
            $preceding = []; $scopedPreceding = [];
            foreach ($drawRows->groupBy('round')->sortKeys() as $roundRows) {
                foreach ($roundRows as $after) {
                    $keys = ($draft['round_progression'] ?? 'team_ready') === 'all_round' ? ['all'] : $after['teams'];
                    foreach ($keys as $key) {
                        foreach (array_filter([$preceding[$key] ?? null, $scopedPreceding[$key] ?? null]) as $before) {
                            if ($before['end']->copy()->addMinutes($rest)->gt($after['start'])) $add('error', 'team_progression', 'A later round starts before its preceding team tie and configured rest finish.', [$before, $after]);
                        }
                    }
                }
                foreach ($roundRows as $before) foreach (array_merge(['all'], $before['teams']) as $key) {
                    if (! isset($preceding[$key]) || $before['end']->gt($preceding[$key]['end'])) $preceding[$key] = $before;
                    if ($this->inScope($before, $scope) && (! isset($scopedPreceding[$key]) || $before['end']->gt($scopedPreceding[$key]['end']))) $scopedPreceding[$key] = $before;
                }
            }
        }
        $checked = count(array_filter($rows, fn ($row) => $row['own'] && $this->inScope($row, $scope)));
        return ['scope' => $scope, 'checked' => $checked, 'compared_event_matches' => count($rows) - $externalCount, 'external_bookings' => $externalCount,
            'errors' => $counts['errors'], 'warnings' => $counts['warnings'], 'unscheduled' => $unscheduled, 'rest_minutes' => $rest, 'court_gap_minutes' => $gap,
            'issues' => $issues, 'omitted_issues' => $counts['errors'] + $counts['warnings'] - count($issues),
            'incomplete' => $incomplete, 'coverage' => 'Saved event matches and nearby outside-event bookings sharing a court or resolved player. Unresolved players and unrecorded bookings cannot be fully checked. Undated matches are counted across the selected draw or event, regardless of day/venue filters.'];
    }

    private function row(Fixture|TeamFixture $fixture, string $drawName, bool $team, bool $own): array
    {
        $slot = $team ? $fixture : $fixture->orderOfPlay;
        $start = Carbon::parse($team ? $fixture->scheduled_at : $slot->time);
        $raw = (int) ($team ? $fixture->duration_min : $slot->duration_minutes);
        $players = $team ? app(UnifiedTeamScheduleService::class)->participants($fixture)
            : collect($fixture->registration1?->players ?? [])->concat($fixture->registration2?->players ?? [])->pluck('id')->map(fn ($id) => 'profile:'.$id)->unique()->values()->all();
        $expected = $team ? 2 * (int) ($fixture->player_count_per_team ?: ($fixture->isDoubles() ? 2 : 1)) : max(2, 2 * max(count($fixture->registration1?->players ?? []), count($fixture->registration2?->players ?? [])));
        return ['key' => ($team ? 'team:' : 'individual:').$fixture->id, 'draw' => (int) $fixture->draw_id,
            'round' => (int) ($team ? $fixture->round_nr : $fixture->round), 'label' => $drawName.' · Round '.($team ? $fixture->round_nr : $fixture->round).($team && $fixture->tie_nr ? ' · Tie '.$fixture->tie_nr : '').' · Match '.$fixture->match_nr.' ('.($team ? 'team' : 'individual').' #'.$fixture->id.')',
            'start' => $start, 'end' => $start->copy()->addMinutes($raw > 0 ? $raw : ($team ? 120 : 75)), 'raw_duration' => $raw,
            'venue' => (int) $slot->venue_id, 'court' => (string) ($team ? $fixture->court_label : $slot->court), 'players' => $players, 'expected_players' => $expected, 'teams' => $team ? ($fixture->teamTie ? array_filter([$fixture->teamTie->home_team_id ? 'team:'.$fixture->teamTie->home_team_id : null, $fixture->teamTie->away_team_id ? 'team:'.$fixture->teamTie->away_team_id : null]) : array_map(fn ($id) => 'region:'.$id, array_filter([$fixture->region1, $fixture->region2]))) : [], 'own' => $own];
    }

    private function inScope(array $row, array $scope): bool
    {
        return (empty($scope['date']) || $scope['date'] === 'all' || $row['start']->toDateString() === $scope['date'])
            && (empty($scope['draw_id']) || $row['draw'] === (int) $scope['draw_id']) && (empty($scope['venue_id']) || $row['venue'] === (int) $scope['venue_id']);
    }

    private function pairs(array $bookings, int $padding, int &$comparisons, callable $inspect): bool
    {
        usort($bookings, fn ($left, $right) => $left['start'] <=> $right['start']);
        $active = [];
        foreach ($bookings as $after) {
            $active = array_filter($active, fn ($before) => $before['end']->copy()->addMinutes($padding)->gt($after['start']));
            foreach ($active as $before) {
                if (++$comparisons > 250000) return false;
                if ($before['own'] || $after['own']) $inspect($before, $after, (int) $before['end']->diffInMinutes($after['start'], false));
            }
            $active[] = $after;
        }
        return true;
    }
}
