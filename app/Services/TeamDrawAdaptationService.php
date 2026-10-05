<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\{Draw, DrawAuditLog, Event, Team, TeamEventFormatRubber, TeamFixture, TeamTie, Venue};
use App\Services\Scheduling\UnifiedTeamScheduleService;
use Illuminate\Support\Facades\DB;

/** Reconcile upcoming competition work while retaining played identities and bookings. */
class TeamDrawAdaptationService
{
    public function lockEvent(int $eventId): Event
    {
        Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
        return Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
    }

    public function adaptEvent(Event|int $event): array
    {
        $eventId = $event instanceof Event ? $event->id : $event;
        return DB::transaction(function () use ($eventId) {
            $this->lockEvent($eventId);
            $reports = [];
            foreach (Draw::where('event_id', $eventId)->orderBy('id')->lockForUpdate()->get() as $draw) {
                if ($draw->isTeamDraw()) $reports[$draw->id] = $this->adaptDraw($draw);
            }
            return $reports;
        });
    }

    public function adaptDraw(Draw $draw, ?array $explicitTeamIds = null, bool $refreshLineups = true, bool $initialize = false, array $changedFixtureIds = []): array
    {
        return DB::transaction(function () use ($draw, $explicitTeamIds, $refreshLineups, $initialize, $changedFixtureIds) {
            $event = $this->lockEvent($draw->event_id);
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $report = ['added_fixture_ids' => [], 'cleared_fixture_ids' => [], 'removed_fixture_ids' => [],
                'removed_bookings' => [], 'updated_lineups' => 0, 'retained_protected_ids' => [], 'review_required' => false, 'review_tie_ids' => [], 'warnings' => []];
            if ($draw->locked || !$draw->isTeamDraw()) return $report;
            $calendar = app(UnifiedTeamScheduleService::class);
            $ties = $draw->teamTies()->with(['rubbers.fixtureResults', 'rubbers.fixturePlayers', 'homeTeam', 'awayTeam'])
                ->orderBy('id')->lockForUpdate()->get();
            // Legacy region fixtures have no source-team identity; do not guess their lineups.
            if ($ties->isEmpty() && TeamFixture::where('draw_id', $draw->id)->whereNull('team_tie_id')->exists()) {
                $report['review_required'] = true;
                $report['warnings'][] = 'Legacy regional fixtures need an organiser review after roster changes; their source-team identities are not recorded. Existing bookings and results were retained.';
                $last = DrawAuditLog::where('draw_id', $draw->id)->where('action', 'team_draw_adapted')->latest('id')->first();
                if (($last?->payload['report'] ?? null) !== $report) DrawAuditLog::record($draw->id, 'team_draw_adapted', null, ['report' => $report]);
                return $report;
            }
            if ($ties->isEmpty() && !$initialize) {
                if ($explicitTeamIds !== null) {
                    $teams = $this->selectedTeams($draw, $explicitTeamIds, $report);
                    $draw->teams_in_draw()->sync($teams->pluck('id')->all());
                }
                return $report;
            }
            if ($draw->team_format_snapshot === null && $draw->teamEventFormat) {
                $draw->forceFill(['team_format_snapshot' => $draw->teamEventFormat->load('rubbers')->toArray()])->save();
            }
            $changedIds = $changedFixtureIds;
            if ($refreshLineups) {
                $teams = $this->selectedTeams($draw, $explicitTeamIds, $report);
                $this->adaptDerivedFormat($draw, $teams, $ties);
                $ids = $teams->pluck('id')->map(fn ($id) => (int) $id)->all();
                $draw->teams_in_draw()->sync($ids);
                $pairs = [];
                foreach ($ties as $tie) {
                    $protected = $tie->isCompleted() || $tie->rubbers->contains(fn ($fx) => $calendar->protected($fx));
                    if (!in_array((int) $tie->home_team_id, $ids, true) || !in_array((int) $tie->away_team_id, $ids, true)) {
                        if ($protected) {
                            foreach ($tie->rubbers as $fixture) {
                                if ($calendar->protected($fixture)) $report['retained_protected_ids'][] = $fixture->id;
                                else { $report['removed_fixture_ids'][] = $fixture->id;
                                    if ($fixture->scheduled_at) $report['removed_bookings'][$fixture->id] = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
                                    $fixture->fixturePlayers()->delete(); $fixture->delete(); }
                            }
                            $report['warnings'][] = "Tie #{$tie->id} retains played history for a removed team.";
                        } else {
                            $report['removed_fixture_ids'] = array_merge($report['removed_fixture_ids'], $tie->rubbers->pluck('id')->all());
                            foreach ($tie->rubbers as $fixture) {
                                if ($fixture->scheduled_at) $report['removed_bookings'][$fixture->id] = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
                                $fixture->fixturePlayers()->delete();
                            }
                            $tie->rubbers()->delete();
                            $tie->delete();
                        }
                        continue;
                    }
                    $pairs[$this->pairKey($tie->home_team_id, $tie->away_team_id)] = true;
                    $this->reconcileRubbers($draw, $tie, $report);
                    $tie->load(['rubbers.fixtureResults', 'rubbers.fixturePlayers']);
                    if ($refreshLineups) {
                        foreach ($tie->rubbers as $fixture) {
                            if ($calendar->protected($fixture)) continue;
                            if ($this->refreshLineup($draw, $tie, $fixture)) {
                                $report['updated_lineups']++;
                                $changedIds[] = $fixture->id;
                            }
                        }
                    }
                }
                $templates = $this->templates($draw);
                $round = (int) $draw->teamTies()->max('round_nr');
                foreach ($teams->values() as $index => $home) {
                    foreach ($teams->values()->slice($index + 1) as $away) {
                        if (isset($pairs[$this->pairKey($home->id, $away->id)]) || !$templates) continue;
                        // Appended rounds never alter existing round/fixture identities or participant order.
                        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => ++$round, 'tie_nr' => 1,
                            'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => TeamTie::STATUS_DRAFT,
                            'format_snapshot' => $draw->team_format_snapshot]);
                        $report['review_tie_ids'][] = $tie->id;
                        $report['warnings'][] = "New tie #{$tie->id} needs organiser review and publication before public visibility.";
                        foreach ($templates as $template) {
                            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                                'round_nr' => $round, 'tie_nr' => 1, 'rubber_sequence' => $template->sequence,
                                'match_nr' => $template->sequence, 'fixture_type' => RubberType::toLegacyFixtureType($template->rubber_code),
                                'rubber_code' => $template->rubber_code, 'rubber_name' => $template->name,
                                'gender_rule' => $template->gender_rule, 'player_count_per_team' => $template->playerCountPerTeam(),
                                'numSets' => 3, 'match_status' => 0]);
                            app(TeamPlayerAutoAssignService::class)->assignForRubber($fixture, $tie, $template);
                            $report['added_fixture_ids'][] = $fixture->id;
                        }
                    }
                }
            }
            $saved = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
            $progression = $saved['round_progression'] ?? 'team_ready';
            $clearedKeys = array_map(fn ($id) => 'team:'.$id, $report['added_fixture_ids']);
            $nodes = $calendar->nodes($draw->fresh(), $progression);
            // Iterate in dependency order. Clearing a prerequisite also clears its unplayed successors.
            foreach ($nodes as $node) {
                $fixture = $node['fixture'];
                if (!$fixture->scheduled_at || $calendar->protected($fixture)) continue;
                $affected = in_array($fixture->id, $changedIds, true) || array_intersect($clearedKeys, $node['dependencies']);
                if (!$affected) continue;
                $error = $calendar->manualError($event, $fixture, ['scheduled_at' => $fixture->scheduled_at,
                    'venue_id' => $fixture->venue_id, 'court' => $fixture->court_label,
                    'duration' => $fixture->duration_min ?: 120, 'court_gap' => $fixture->gap_minutes ?? 0,
                    'player_rest' => $saved['player_rest'] ?? 60, 'round_progression' => $progression, 'adaptation' => true]);
                if (!$error) {
                    $report['warnings'] = array_merge($report['warnings'], $calendar->warnings($fixture,
                        ['scheduled_at' => $fixture->scheduled_at, 'venue_id' => $fixture->venue_id]));
                    continue;
                }
                $before = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
                $fixture->forceFill(['scheduled_at' => null, 'venue_id' => null, 'court_label' => null,
                    'duration_min' => null, 'gap_minutes' => 0, 'scheduled' => 0, 'clash_flag' => true])->save();
                $report['cleared_fixture_ids'][] = $fixture->id;
                $clearedKeys[] = 'team:'.$fixture->id;
                $report['warnings'][] = "Rubber #{$fixture->id} returned to planning: {$error}";
                DrawAuditLog::record($draw->id, 'team_schedule_adaptation_cleared', null,
                    ['fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'before' => $before, 'reason' => $error]);
            }
            if ($report['added_fixture_ids']) $report['warnings'][] = count($report['added_fixture_ids']).' new matches need scheduling.';
            $report['warnings'] = array_values(array_unique($report['warnings']));
            if ($report['updated_lineups'] || $report['added_fixture_ids'] || $report['removed_fixture_ids'] || $report['cleared_fixture_ids'] || $report['review_required']) {
                $last = DrawAuditLog::where('draw_id', $draw->id)->where('action', 'team_draw_adapted')->latest('id')->first();
                if (($last?->payload['report'] ?? null) !== $report) DrawAuditLog::record($draw->id, 'team_draw_adapted', null, ['report' => $report]);
            }
            return $report;
        });
    }

    private function pairKey(int $home, int $away): string
    {
        return min($home, $away).':'.max($home, $away);
    }

    private function templates(Draw $draw): array
    {
        $rows = $draw->team_format_snapshot['rubbers'] ?? $draw->teamEventFormat?->rubbers?->map->toArray()->all() ?? [];
        return array_values(array_map(fn ($row) => new TeamEventFormatRubber($row), array_filter($rows, fn ($row) => !($row['retired'] ?? false))));
    }

    private function adaptDerivedFormat(Draw $draw, $teams, $ties): void
    {
        $snapshot = $draw->team_format_snapshot;
        $code = $draw->team_draw_selection['rubber_code'] ?? null;
        if ($draw->team_event_format_id || !$code || !str_starts_with($snapshot['name'] ?? '', 'Roster positions')) return;
        $resolver = app(TeamDrawSideResolver::class);
        $sides = $teams->map(fn ($team) => $resolver->side($draw, $team));
        $maxRank = max(1, (int) $sides->flatMap(fn ($team) => $team->team_players->concat($team->team_players_no_profile))->max('rank'));
        $doubles = in_array($code, [RubberType::DOUBLES, RubberType::MIXED_DOUBLES], true);
        $active = [];
        for ($rank = 1; $rank <= $maxRank; $rank += $doubles ? 2 : 1) {
            $positions = $doubles ? [$rank, $rank + 1] : [$rank];
            $active[] = ['sequence' => count($active) + 1, 'rubber_code' => $code,
                'name' => ucfirst(str_replace('_', ' ', $code)).' '.(count($active) + 1),
                'player_count_per_team' => count($positions), 'home_positions' => $positions,
                'away_positions' => $code === RubberType::REVERSE_SINGLES ? [$rank % 2 ? $rank + 1 : $rank - 1] : $positions,
                'gender_rule' => $code === RubberType::MIXED_DOUBLES ? 'mixed' : null, 'is_required' => true];
        }
        $original = $snapshot;
        $snapshot['rubbers'] = $active;
        if ($original !== $snapshot) {
            foreach ($ties as $tie) {
                if ($tie->format_snapshot === null) $tie->forceFill(['format_snapshot' => $original])->save();
            }
        }
        if ($draw->team_format_snapshot !== $snapshot) $draw->forceFill(['team_format_snapshot' => $snapshot])->save();
    }

    private function reconcileRubbers(Draw $draw, TeamTie $tie, array &$report): void
    {
        $templates = $this->templates($draw);
        $sequences = array_map(fn ($template) => (int) $template->sequence, $templates);
        $calendar = app(UnifiedTeamScheduleService::class);
        $existingSequences = $tie->rubbers->pluck('rubber_sequence')->map(fn ($value) => (int) $value)->all();
        $structural = array_diff($sequences, $existingSequences) || array_diff($existingSequences, $sequences);
        if ($structural && ($tie->isCompleted() || $tie->rubbers->contains(fn ($fixture) => $calendar->protected($fixture)))) {
            $report['review_required'] = true;
            $report['warnings'][] = "Tie #{$tie->id} retains its started format; changed roster positions need organiser review.";
            return;
        }
        if ($structural && $tie->status !== TeamTie::STATUS_DRAFT) {
            $tie->forceFill(['status' => TeamTie::STATUS_DRAFT, 'published_at' => null])->save();
            $report['review_tie_ids'][] = $tie->id;
            $report['warnings'][] = "Tie #{$tie->id} needs review and publication after changing roster positions.";
        }
        $tie->forceFill(['format_snapshot' => $draw->team_format_snapshot])->save();
        foreach ($tie->rubbers as $fixture) {
            if (in_array((int) $fixture->rubber_sequence, $sequences, true) || $calendar->protected($fixture)) continue;
            $report['removed_fixture_ids'][] = $fixture->id;
            if ($fixture->scheduled_at) $report['removed_bookings'][$fixture->id] = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
            $fixture->fixturePlayers()->delete();
            $fixture->delete();
        }
        foreach ($templates as $template) {
            if ($tie->rubbers()->where('rubber_sequence', $template->sequence)->exists()) continue;
            if ($tie->isCompleted() || $tie->rubbers->contains(fn ($fixture) => $calendar->protected($fixture))) {
                $report['warnings'][] = "Tie #{$tie->id} retains its started format; new roster positions apply to upcoming ties.";
                continue;
            }
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                'round_nr' => $tie->round_nr, 'tie_nr' => $tie->tie_nr, 'rubber_sequence' => $template->sequence,
                'match_nr' => $template->sequence, 'fixture_type' => RubberType::toLegacyFixtureType($template->rubber_code),
                'rubber_code' => $template->rubber_code, 'rubber_name' => $template->name,
                'gender_rule' => $template->gender_rule, 'player_count_per_team' => $template->playerCountPerTeam(), 'numSets' => 3, 'match_status' => 0]);
            app(TeamPlayerAutoAssignService::class)->assignForRubber($fixture, $tie, $template);
            $report['added_fixture_ids'][] = $fixture->id;
            if ($tie->status !== TeamTie::STATUS_DRAFT) {
                $tie->forceFill(['status' => TeamTie::STATUS_DRAFT, 'published_at' => null])->save();
                $report['warnings'][] = "Tie #{$tie->id} needs review and publication after adding roster positions.";
            }
        }
    }

    private function refreshLineup(Draw $draw, TeamTie $tie, TeamFixture $fixture): bool
    {
        $template = collect(($tie->format_snapshot ?? $draw->team_format_snapshot)['rubbers'] ?? [])->map(fn ($attributes) => new TeamEventFormatRubber($attributes))->first(fn ($row) => (int) $row->sequence === (int) $fixture->rubber_sequence);
        if (!$template) return false;
        $resolver = app(TeamDrawSideResolver::class);
        $slots = app(TeamPlayerAutoAssignService::class)->resolveSlots($template,
            $resolver->side($draw, $tie->homeTeam, $fixture->round_nr, $fixture->id),
            $resolver->side($draw, $tie->awayTeam, $fixture->round_nr, $fixture->id));
        $changed = false;
        foreach ($slots as $data) {
            $row = $fixture->fixturePlayers()->firstOrNew(['slot_no' => $data['slot_no']]);
            foreach ([1, 2] as $side) {
                if ($row->{'team'.$side.'_id'} != $data['team'.$side.'_id'] || $row->{'team'.$side.'_no_profile_id'} != $data['team'.$side.'_no_profile_id']) {
                    $snapshot = $row->participant_snapshot ?? [];
                    unset($snapshot[$side]);
                    $row->participant_snapshot = $snapshot;
                }
            }
            $row->fill($data);
            if ($row->isDirty() || !$row->exists) { $row->save(); $changed = true; }
        }
        if ($changed) app(TeamParticipantHistoryService::class)->captureFixture($fixture->fresh());
        return $changed;
    }

    private function selectedTeams(Draw $draw, ?array $explicitTeamIds, array &$report)
    {
        $selection = $draw->team_draw_selection;
        $query = Team::with(['category.category', 'team_players.player', 'team_players_no_profile'])
            ->where(fn ($q) => $q->whereHas('category', fn ($category) => $category->where('event_id', $draw->event_id))
                ->when(!$selection || $explicitTeamIds !== null, fn ($q) => $q->orWhereNull('category_event_id')))->orderBy('id');
        if ($selection && $explicitTeamIds === null) $query->whereIn('category_event_id', $selection['category_ids']);
        else {
            $members = $draw->teams_in_draw()->pluck('teams.id')->all();
            if (!$members && $explicitTeamIds === null) {
                $members = $draw->teamTies()->get(['home_team_id', 'away_team_id'])
                    ->flatMap(fn ($tie) => [$tie->home_team_id, $tie->away_team_id])->unique()->all();
            }
            $query->whereIn('id', $explicitTeamIds ?? $members);
        }
        $teams = $query->get();
        if ($explicitTeamIds !== null && $teams->count() !== count(array_unique(array_map('intval', $explicitTeamIds)))) {
            throw new \InvalidArgumentException('Every selected team must belong to this event.');
        }
        if (($selection['rubber_code'] ?? null) !== RubberType::MIXED_DOUBLES) return $teams;
        $resolver = app(TeamDrawSideResolver::class);
        $map = []; $representatives = collect();
        foreach ($teams->groupBy('region_id') as $region => $sources) {
            $boys = $sources->filter(fn ($t) => $resolver->categoryKey($t->category->category->name)['gender'] === 'boys');
            $girls = $sources->filter(fn ($t) => $resolver->categoryKey($t->category->category->name)['gender'] === 'girls');
            if (!$region || $boys->count() !== 1 || $girls->count() !== 1) {
                $report['warnings'][] = 'A mixed side has a missing or ambiguous boys/girls partner and remains outside upcoming ties.';
                continue;
            }
            $boy = $boys->first(); $girl = $girls->first();
            $map[$boy->id] = ['boys' => $boy->id, 'girls' => $girl->id, 'region_id' => (int) $region, 'name' => $boy->name.' + '.$girl->name];
            $representatives->push($boy);
        }
        // Keep historical side labels for played ties whose source is no longer eligible.
        $selection['mixed_sides'] = $map + ($selection['mixed_sides'] ?? []);
        $draw->forceFill(['team_draw_selection' => $selection])->save();
        return $representatives;
    }
}
