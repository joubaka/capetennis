<?php

namespace App\Services\Performance;

use App\Models\{Event, Fixture, Player, Registration, TeamFixture};
use App\Services\TeamRubberResultService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Version 2: streamed historical evidence; every event has one weight per cohort. */
class PlayerPerformanceHistoryService
{
    public function forPlayer(Player $player, CarbonImmutable $asOf, PlayerPerformancePilotService $pilot): array
    {
        $states = [];
        foreach (['singles', 'doubles'] as $discipline) {
            $states[$discipline] = ['totals' => [], 'evidence' => collect(), 'match_evidence' => collect(), 'reasons' => [], 'finish_count' => 0, 'match_count' => 0, 'evidence_count' => 0, 'match_evidence_count' => 0];
        }
        $registrations = Registration::query()->select('id')->whereHas('players', fn ($query) => $query->whereKey($player->id));
        $events = Event::query()->with('eventTypeModel')->whereDate('start_date', '<=', $asOf->toDateString())
            ->where(function ($query) use ($registrations, $player) {
                $query->whereExists(fn ($results) => $results->selectRaw('1')->from('category_results')->whereColumn('category_results.event_id', 'events.id')->whereIn('registration_id', $registrations))
                    ->orWhereExists(fn ($members) => $members->selectRaw('1')->from('category_event_registrations')->join('category_events', 'category_events.id', '=', 'category_event_registrations.category_event_id')->whereColumn('category_events.event_id', 'events.id')->whereIn('registration_id', $registrations))
                    ->orWhereHas('draws', fn ($draws) => $draws->whereHas('drawFixtures', fn ($fixtures) => $fixtures->where(fn ($fixtures) => $fixtures->whereIn('registration1_id', $registrations)->orWhereIn('registration2_id', $registrations)))
                        ->orWhereHas('fixtures', fn ($fixtures) => $fixtures->whereHas('fixturePlayers', fn ($players) => $players->where('team1_id', $player->id)->orWhere('team2_id', $player->id)
                            ->orWhereHas('noProfile1', fn ($linked) => $linked->where('player_profile', $player->id))->orWhereHas('noProfile2', fn ($linked) => $linked->where('player_profile', $player->id)))));
            });
        foreach ($events->lazyById(25) as $event) {
            // Published results in ongoing events use today's as-of date until the event has ended.
            if (CarbonImmutable::parse($event->end_date)->greaterThan($asOf)) { $event->end_date = $asOf->toDateString(); }
            $components = ['singles' => [], 'doubles' => []];
            foreach (['singles', 'doubles'] as $discipline) {
                $preview = $pilot->preview([], $discipline, $asOf, 12, $player, $event->id);
                foreach ($preview['evidence'] as $row) {
                    $key = $this->cohortKey($row['cohort'], $row['tier']);
                    $this->retain($states[$discipline], 'evidence', $row);
                    if ($row['reason'] !== null) {
                        $states[$discipline]['reasons'][$row['reason']] = ($states[$discipline]['reasons'][$row['reason']] ?? 0) + 1;
                    } elseif ($row['points'] !== null) {
                        $components[$discipline][$key]['finishes'][] = $row['points'];
                        $components[$discipline][$key]['cohort'] = $row['cohort'];
                        $components[$discipline][$key]['band'] = $row['tier'] === 'Open' ? 'Open' : 'A/B';
                    }
                }
            }
            foreach ($this->individualMatches($event, $player, $pilot) as $match) {
                $this->addMatch($states, $components, $match);
            }
            foreach ($this->teamMatches($event, $player) as $match) {
                $this->addMatch($states, $components, $match);
            }
            $date = CarbonImmutable::parse($event->end_date)->toDateString();
            $weight = max(PHP_FLOAT_MIN, pow(0.5, max(0, CarbonImmutable::parse($date)->diffInDays($asOf, false)) / 180));
            foreach ($components as $discipline => $cohorts) {
                foreach ($cohorts as $key => $component) {
                    $finishes = $component['finishes'] ?? [];
                    $matchCount = $component['match_count'] ?? 0;
                    $parts = [];
                    if ($finishes) { $parts[] = array_sum($finishes) / count($finishes); }
                    if ($matchCount) { $parts[] = $component['match_points'] / $matchCount; }
                    if (!$parts) { continue; }
                    $total = &$states[$discipline]['totals'][$key];
                    $total ??= ['cohort' => $component['cohort'], 'band' => $component['band'], 'weighted' => 0, 'weight' => 0, 'count' => 0, 'finish_count' => 0, 'match_count' => 0, 'wins' => 0, 'losses' => 0, 'last_played' => $date];
                    $total['weighted'] += array_sum($parts) / count($parts) * $weight;
                    $total['weight'] += $weight;
                    $total['count']++;
                    $total['finish_count'] += count($finishes);
                    $total['match_count'] += $matchCount;
                    $wins = $component['match_wins'] ?? 0;
                    $total['wins'] += $wins;
                    $total['losses'] += $matchCount - $wins;
                    $total['last_played'] = max($date, $total['last_played']);
                    unset($total);
                }
            }
        }
        $disciplines = [];
        foreach ($states as $discipline => $state) {
            $cohorts = collect($state['totals'])->map(fn ($total) => $total + ['score' => round($total['weighted'] / $total['weight'], 1)])
                ->sort(fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'].' '.$a['band'], $b['cohort'].' '.$b['band']))->values();
            $state['finish_count'] = $cohorts->sum('finish_count');
            $state['match_count'] = $cohorts->sum('match_count');
            $disciplines[$discipline] = $state + ['cohorts' => $cohorts, 'headline' => $cohorts->first(), 'truncated' => false];
        }
        return ['disciplines' => $disciplines, 'headline' => $disciplines['singles']['headline'], 'as_of' => $asOf->toDateString(), 'method_version' => 2];
    }

    private function cohortKey(?string $cohort, ?string $tier): string
    {
        return ($cohort ?? 'unknown').'|'.($tier === 'Open' ? 'Open' : 'A/B');
    }

    private function retain(array &$state, string $type, array $row): void
    {
        $state[$type.'_count']++;
        $state[$type]->push($row);
        $state[$type] = $state[$type]->sort(fn ($a, $b) => strcmp($b['date'], $a['date']) ?: (($b['fixture_id'] ?? $b['event_id']) <=> ($a['fixture_id'] ?? $a['event_id'])))->take(50)->values();
    }

    private function addMatch(array &$states, array &$components, array $match): void
    {
        $discipline = $match['discipline'];
        $this->retain($states[$discipline], 'match_evidence', $match);
        if ($match['reason'] !== null) {
            $states[$discipline]['reasons'][$match['reason']] = ($states[$discipline]['reasons'][$match['reason']] ?? 0) + 1;
            return;
        }
        $key = $this->cohortKey($match['cohort'], $match['tier']);
        $components[$discipline][$key]['match_count'] = ($components[$discipline][$key]['match_count'] ?? 0) + 1;
        $components[$discipline][$key]['match_points'] = ($components[$discipline][$key]['match_points'] ?? 0) + $match['points'];
        $components[$discipline][$key]['match_wins'] = ($components[$discipline][$key]['match_wins'] ?? 0) + (int) $match['won'];
        $components[$discipline][$key]['cohort'] = $match['cohort'];
        $components[$discipline][$key]['band'] = $match['tier'] === 'Open' ? 'Open' : 'A/B';
    }

    public function individualMatches(Event $event, ?Player $player, PlayerPerformancePilotService $pilot, ?CarbonImmutable $asOf = null, bool $sharedNetwork = false): \Generator
    {
        if (!$event->published) { return; }
        $cache = [];
        $definitions = []; $memberships = []; $legacyFields = null; $registrationFields = [];
        if ($sharedNetwork) {
            $legacyFields = \App\Models\CategoryEvent::query()->with(['category', 'categoryEventRegistrations' => fn ($query) => $query->withTrashed()->limit(257)])
                ->where('event_id', $event->id)->limit(257)->get()->keyBy('id');
            if ($legacyFields->count() > 256) { throw new \OverflowException('Individual source field limit exceeded'); }
            foreach ($legacyFields->groupBy('category_id') as $categoryId => $sameCategory) { $definitions[$categoryId] = $sameCategory; }
            foreach ($legacyFields as $legacyField) {
                $memberships[$legacyField->id] = $legacyField->categoryEventRegistrations->pluck('registration_id');
                foreach ($memberships[$legacyField->id] as $registrationId) { $registrationFields[$registrationId][] = $legacyField->id; }
            }
        }
        $query = Fixture::query()->with(['draw.categoryEvent.category', 'draw.settings', 'registration1.players', 'registration2.players', 'fixtureResults', 'oop'])
            ->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id)->where('published', true))
            ->when(!$player, fn ($query) => $query->whereIn('match_status', $sharedNetwork ? [0,1,2,3] : [1,2,3])->whereHas('fixtureResults'))
            ->when($player, fn ($query) => $query->where(fn ($query) => $query->whereHas('registration1.players', fn ($players) => $players->whereKey($player->id))->orWhereHas('registration2.players', fn ($players) => $players->whereKey($player->id))));
        foreach ($query->lazyById(50) as $fixture) {
            $first = $fixture->registration1?->players ?? collect();
            $second = $fixture->registration2?->players ?? collect();
            $discipline = $first->count() === 2 && $second->count() === 2 ? 'doubles' : 'singles';
            $field = $fixture->draw->categoryEvent;
            if (!$field || (int) $field->event_id !== (int) $event->id) {
                if ($sharedNetwork) {
                    $ids = array_intersect($registrationFields[$fixture->registration1_id] ?? [], $registrationFields[$fixture->registration2_id] ?? []);
                    $candidates = $legacyFields->only($ids);
                    if ($field) { $candidates = $candidates->where('category_id', $field->category_id); }
                } elseif ($field) {
                    $definitions[$field->category_id] ??= \App\Models\CategoryEvent::query()->with(['category', 'event.eventTypeModel'])
                        ->where('event_id', $event->id)->where('category_id', $field->category_id)->limit(2)->get();
                    $candidates = $definitions[$field->category_id];
                } else {
                    $candidates = \App\Models\CategoryEvent::query()->with(['category', 'event.eventTypeModel'])->where('event_id', $event->id)
                        ->whereHas('categoryEventRegistrations', fn ($members) => $members->withTrashed()->where('registration_id', $fixture->registration1_id))
                        ->whereHas('categoryEventRegistrations', fn ($members) => $members->withTrashed()->where('registration_id', $fixture->registration2_id))->limit(2)->get();
                }
                $field = $candidates->count() === 1 ? $candidates->first() : null;
            }
            $division = $field ? $pilot->fieldDivision($field, $cache) : ['tier' => 'Open', 'cohort' => mb_strtolower(trim($fixture->draw->drawName)), 'reason' => 'Match category cannot be verified'];
            $reason = $division['reason'];
            if (!$field || (int) $field->event_id !== (int) $event->id) { $reason = 'Match category/event does not match'; }
            if ($field) {
                $definitions[$field->category_id] ??= \App\Models\CategoryEvent::query()->where('event_id', $event->id)->where('category_id', $field->category_id)->limit(2)->get();
                $memberships[$field->id] ??= $field->categoryEventRegistrations()->withTrashed()->limit(257)->pluck('registration_id');
            }
            if ($field && $definitions[$field->category_id]->count() !== 1) {
                $reason = 'Multiple category definitions make this match cohort ambiguous';
            }
            if ($first->count() !== ($discipline === 'singles' ? 1 : 2) || $second->count() !== $first->count() || $first->pluck('id')->merge($second->pluck('id'))->unique()->count() !== $first->count() + $second->count()) { $reason = 'Match participants are missing, duplicated or ambiguous'; }
            if ($field && ($memberships[$field->id]->count() > 256 || !$memberships[$field->id]->contains($fixture->registration1_id) || !$memberships[$field->id]->contains($fixture->registration2_id))) { $reason = 'Match registrations do not belong to this event category or its roster exceeds the validation limit'; }
            $sets = $fixture->fixtureResults;
            $winner = $this->completedWinner($sets, 'registration1_score', 'registration2_score');
            $validation = app(\App\Domain\Draws\Services\ScoreValidationService::class)->validate($fixture,
                $sets->sortBy('set_nr')->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->all());
            $format = $fixture->draw->settings?->score_format;
            $hasPreset = $format && in_array($format, \App\Domain\Draws\Services\TennisScoreFormat::keys(), true);
            $credibleLegacy = $sets->every(fn ($set) => $this->legacyTerminalSet((int) $set->registration1_score, (int) $set->registration2_score));
            $declared = (int) $fixture->winner_registration;
            $expected = $winner === 1 ? (int) $fixture->registration1_id : ($winner === 2 ? (int) $fixture->registration2_id : 0);
            $legacyRr = $fixture->stage === 'RR' && ($fixture->draw->settings?->workflow === null || $fixture->draw->settings?->workflow === 'round_robin');
            $winsNeeded = $hasPreset ? \App\Domain\Draws\Services\TennisScoreFormat::get($format)['wins_needed'] : (int) ceil(max(1, min(5, (int) ($fixture->draw->settings?->num_sets ?: 3))) / 2);
            $maxWins = max($sets->filter(fn ($set) => $set->registration1_score > $set->registration2_score)->count(), $sets->filter(fn ($set) => $set->registration2_score > $set->registration1_score)->count());
            if (!in_array((int) $fixture->match_status, $sharedNetwork ? [0, 1, 2, 3] : [1, 2, 3], true) || !$expected || !$validation['valid'] || (!$hasPreset && !$credibleLegacy) || ($declared && $declared !== $expected)
                || ((int) $fixture->match_status === 0 && (!$sharedNetwork || !$this->completeSequence($sets, $winsNeeded, 'registration1_score', 'registration2_score')))
                || ((int) $fixture->match_status === 2 && (!$legacyRr || (!$hasPreset && $maxWins < $winsNeeded)))
                || ((int) $fixture->match_status === 3 && ($declared !== $expected || (!$hasPreset && $maxWins < $winsNeeded)))) {
                $reason = 'Match is unfinished, unplayed or has inconsistent winner/score evidence';
            }
            $dateEvidence = $this->confidenceDate($fixture, $event, $asOf ?? CarbonImmutable::today());
            if ($sharedNetwork && $dateEvidence['confidence_future']) { $reason = 'Match schedule is later than the snapshot date'; }
            $targetFirst = !$player || $first->contains('id', $player->id);
            $cohort = $division['cohort'];
            if ($event->frontend_type_view === 'masters') { $cohort = 'masters · '.$cohort; }
            $won = $winner === ($targetFirst ? 1 : 2);
            $tier = $division['tier'];
            $opponents = ($targetFirst ? $second : $first)->map(fn ($opponent) => trim($opponent->name.' '.$opponent->surname))->implode(' / ');
            yield $dateEvidence + ['outcome_target' => app(PlayedMatchMarginPolicy::class)->target($sets->sortBy('set_nr')->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all(), $winner, $hasPreset ? \App\Domain\Draws\Services\TennisScoreFormat::get($format)['set_types'] : null), 'fixture_id' => $fixture->id, 'source' => 'Individual match', 'event_id' => $event->id, 'event' => $event->name, 'date' => CarbonImmutable::parse($event->end_date)->toDateString(), 'category' => $field?->category?->name ?? $fixture->draw->drawName, 'cohort' => $cohort, 'tier' => $tier, 'discipline' => $discipline, 'won' => $won, 'player1_id' => $first->first()?->id, 'player2_id' => $second->first()?->id, 'winner_side' => $winner, 'opponents' => $opponents, 'points' => $reason ? null : ($tier === 'A' ? 50 : 0) + ($tier === 'Open' ? 100 : 50) * (int) $won, 'reason' => $reason, 'score' => $sets->map(fn ($set) => $targetFirst ? $set->registration1_score.'-'.$set->registration2_score : $set->registration2_score.'-'.$set->registration1_score)->implode(' ')];
        }
    }

    /** Scheduled date is a proxy for play; imports/corrections never refresh confidence. */
    private function confidenceDate(Fixture $fixture, Event $event, CarbonImmutable $asOf): array
    {
        return $this->evidenceDate($fixture->oop?->time, $event, $asOf);
    }

    private function evidenceDate($time, Event $event, CarbonImmutable $asOf): array
    {
        $start = CarbonImmutable::parse($event->start_date)->startOfDay();
        $end = CarbonImmutable::parse($event->getRawOriginal('end_date') ?? $event->end_date)->endOfDay();
        $today = $asOf->endOfDay();
        if ($time && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $time)) {
            try {
                $date = CarbonImmutable::parse($time);
                if ($date->betweenIncluded($start, $end) && $date->greaterThan($today)) {
                    return ['confidence_date' => $date->toDateString(), 'confidence_date_basis' => 'future scheduled match date', 'confidence_future' => true];
                }
                if ($date->betweenIncluded($start, $end) && $date->lessThanOrEqualTo($today)) {
                    return ['confidence_date' => $date->toDateString(), 'confidence_date_basis' => 'scheduled match date', 'confidence_future' => false];
                }
            } catch (\Throwable) { /* Invalid legacy schedule: use the conservative event proxy. */ }
        }
        return ['confidence_date' => $start->toDateString(), 'confidence_date_basis' => 'event start proxy', 'confidence_future' => false];
    }

    /** Reject partial sets, duplicate set numbers, ties and inconsistent set winner totals. */
    private function completedWinner(Collection $sets, string $first, string $second): ?int
    {
        if ($sets->isEmpty() || $sets->count() > 5 || $sets->pluck('set_nr')->unique()->count() !== $sets->count()) { return null; }
        $numbers = $sets->pluck('set_nr')->map(fn ($number) => (int) $number)->sort()->values()->all();
        if ($numbers !== range(1, $sets->count()) && $numbers !== range(0, $sets->count() - 1)) { return null; }
        $wins = [1 => 0, 2 => 0];
        foreach ($sets->sortBy('set_nr')->values() as $index => $set) {
            if (!is_numeric($set->$first) || !is_numeric($set->$second)) { return null; }
            $a = (int) $set->$first; $b = (int) $set->$second;
            if ($a < 0 || $b < 0 || $a > 999 || $b > 999 || $a === $b) { return null; }
            $wins[$a > $b ? 1 : 2]++;
        }
        if ($wins[1] === $wins[2]) { return null; }
        return $wins[1] > $wins[2] ? 1 : 2;
    }

    public function teamMatches(Event $event, ?Player $player, ?CarbonImmutable $asOf = null): \Generator
    {
        if (!$event->published) { return; }
        $network = $player === null; $asOf ??= CarbonImmutable::today();
        if ($network && !TeamFixture::query()->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id))->whereHas('teamResults')->exists()) { return; }
        $regions = $event->regions()->pluck('team_regions.id')->all();
        $teams = \App\Models\Team::query()->without('team_players_no_profile')->with(['category.category', 'regions:id,region_name', 'team_players' => fn ($query) => $query->limit(257)])->where(function ($query) use ($event, $regions, $network) {
            $query->whereHas('category', fn ($field) => $field->where('event_id', $event->id));
            if ($network) { $query->orWhere(fn ($legacy) => $legacy->whereNull('category_event_id')->where('year', CarbonImmutable::parse($event->start_date)->year)->whereIn('region_id', $regions)); }
        })->limit(5001)->get()->keyBy('id');
        if ($teams->count() > 5000 || $teams->contains(fn ($team) => $team->team_players->count() > 256)) { if ($network) { throw new \OverflowException('Team source roster limit exceeded'); } return; }
        $query = TeamFixture::query()->publishedTeamTies()->with(['draw.categoryEvent.category', 'teamTie.homeTeam.category', 'teamTie.awayTeam.category', 'fixturePlayers.player1', 'fixturePlayers.player2', 'teamResults'])
            ->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id)->where('published', true))
            ->when($network, fn ($query) => $query->whereHas('teamResults')->where(fn ($types) => $types->whereIn('fixture_type', [0,1,4])->orWhereNull('fixture_type')->orWhereIn('rubber_code', ['singles','reverse_singles'])))
            ->when($player, fn ($query) => $query->whereHas('fixturePlayers', fn ($players) => $players->where('team1_id', $player->id)->orWhere('team2_id', $player->id)
                ->orWhereHas('noProfile1', fn ($linked) => $linked->where('player_profile', $player->id))->orWhereHas('noProfile2', fn ($linked) => $linked->where('player_profile', $player->id))));
        foreach ($query->lazyById(50) as $fixture) {
            $discipline = $fixture->isDoubles() ? 'doubles' : 'singles';
            $needed = $discipline === 'singles' ? 1 : 2;
            $rows = $fixture->fixturePlayers;
            $ids1 = $rows->pluck('team1_id'); $ids2 = $rows->pluck('team2_id');
            $reason = null; $sourceBySide = []; $resolved = [];
            if ($fixture->teamTie && (int) $fixture->teamTie->draw_id !== (int) $fixture->draw_id) { $reason = 'Team tie does not belong to this draw'; }
            if ($rows->count() !== $needed) { $reason = 'Team match has missing or ambiguous player identities'; }
            foreach ($rows as $row) {
                foreach ([1,2] as $side) {
                    $snapshot = $row->participant_snapshot[$side] ?? null;
                    $source = $fixture->teamTie?->{($side === 1 ? 'homeTeam' : 'awayTeam')};
                    if (!$fixture->teamTie && $fixture->{'region'.$side}) {
                        $matches = $teams->filter(function ($team) use ($fixture, $side, $ids1, $ids2, $network) {
                            if ((int) $team->region_id !== (int) $fixture->{'region'.$side}) { return false; }
                            if (!$network && (int) $team->category_event_id !== (int) $fixture->draw->category_event_id) { return false; }
                            return ($side === 1 ? $ids1 : $ids2)->diff($team->team_players->pluck('player_id'))->isEmpty();
                        });
                        $source = $matches->count() === 1 ? $matches->first() : null;
                    }
                    $map = $fixture->draw->team_draw_selection['mixed_sides'][$source?->id] ?? null;
                    $allowed = $map ? [(int) $map['boys'], (int) $map['girls']] : [(int) $source?->id];
                    if ($snapshot) {
                        $historySource = $teams->get($snapshot['source_team_id'] ?? 0);
                        if (!$historySource || !in_array((int) $historySource->id, $allowed, true)
                            || (!$network && (int) $historySource->category?->event_id !== (int) $event->id)
                            || !app(\App\Services\TeamParticipantHistoryService::class)->matches($row, $side, $historySource, (int) $event->id)) {
                            $reason = 'Team match participant history does not match this event/player/side';
                        } else {
                            $sourceBySide[$side] = $historySource;
                            if ($row->{'team'.$side.'_no_profile_id'}) {
                                $profile = app(ImportedMatchIdentityResolver::class)->resolve($row, $side, $historySource, (int) $event->id);
                                if ($profile) { $resolved[$row->id][$side] = $profile; }
                                else { $reason = 'Imported team identity has no unchanged captured profile attestation'; }
                            }
                        }
                    } else {
                        $validMembers = $teams->filter(fn ($team) => in_array((int) $team->id, $allowed, true)
                            && $team->team_players->contains('player_id', $row->{'team'.$side.'_id'}));
                        $validMember = $validMembers->count() === 1;
                        if ($validMember) { $sourceBySide[$side] = $validMembers->first(); }
                        if (!$validMember) { $reason = 'Team match player cannot be verified against this event/side'; }
                    }
                }
            }
            $ids1 = $rows->map(fn ($row) => $resolved[$row->id][1]->id ?? $row->team1_id);
            $ids2 = $rows->map(fn ($row) => $resolved[$row->id][2]->id ?? $row->team2_id);
            if ($ids1->filter()->count() !== $needed || $ids2->filter()->count() !== $needed || $ids1->merge($ids2)->unique()->count() !== 2 * $needed) { $reason ??= 'Team match has missing or ambiguous player identities'; }
            $winner = $this->completedWinner($fixture->teamResults, 'team1_score', 'team2_score');
            $outcomeFixture = $fixture;
            if ($network && !$fixture->rubber_code && in_array($fixture->fixture_type, [null, 0], true) && $discipline === 'singles'
                && count($sourceBySide) === 2 && preg_match('/\bsingles\b/i', $fixture->draw->drawName) && !preg_match('/doubles|mixed/i', $fixture->draw->drawName)) {
                // Interpret the verified legacy singleton format only in memory; never repair stored types.
                $outcomeFixture = clone $fixture; $outcomeFixture->fixture_type = 1;
            }
            $outcome = app(TeamRubberResultService::class)->outcome($outcomeFixture);
            $terminal = $fixture->teamResults->every(fn ($set) => $this->legacyTerminalSet((int) $set->team1_score, (int) $set->team2_score));
            if (!in_array((int) $fixture->match_status, $network ? [0,1] : [1], true) || !$winner || !$terminal || !$outcome['complete'] || $outcome['winner'] !== ($winner === 1 ? 'home' : 'away')) { $reason = 'Team match is incomplete or has inconsistent score evidence'; }
            if ($network && (int) $fixture->match_status === 0) {
                $neededWins = $fixture->draw->team_scoring_rules !== null ? app(TeamRubberResultService::class)->rules($fixture)['sets_to_win'] : max(1, (int) ceil(($outcomeFixture->numSets ?: ($outcomeFixture->isSingles() ? 3 : 1)) / 2));
                if (!$this->completeSequence($fixture->teamResults, $neededWins, 'team1_score', 'team2_score')) { $reason = 'Legacy team scores do not establish complete play'; }
            }
            $field = $fixture->draw->categoryEvent;
            $category = $field?->category?->name ?? $fixture->draw->drawName;
            $dateEvidence = $this->evidenceDate($fixture->scheduled_at, $event, $asOf);
            if ($network && $dateEvidence['confidence_future']) { $reason = 'Team match schedule is later than the snapshot date'; }
            if ($network && $rows->contains(fn ($row) => !($resolved[$row->id][1] ?? $row->player1) || !($resolved[$row->id][2] ?? $row->player2))) { $reason ??= 'Team match player profile cannot be verified'; }
            $sharedCohort = $this->verifiedTeamCohort($sourceBySide, $event, $fixture->draw->drawName);
            if ((!$field || (int) $field->event_id !== (int) $event->id) && (!$network || !$sharedCohort)) { $reason = 'Team match category/event cannot be verified'; }
            if ($network && !$sharedCohort) { $reason = 'Team singles age/gender context cannot be verified'; }
            if ($network && (!$fixture->isSingles() && !(!$fixture->rubber_code && in_array($fixture->fixture_type, [null, 0], true) && preg_match('/\bsingles\b/i', $fixture->draw->drawName) && !preg_match('/doubles|mixed/i', $fixture->draw->drawName)))) { $reason = 'Legacy team discipline is ambiguous or doubles'; }
            $cohort = 'team · '.mb_strtolower(trim(preg_replace('/\s+/u', ' ', $category)));
            $targetFirst = !$player || $ids1->contains($player->id);
            $won = $winner === ($targetFirst ? 1 : 2);
            $opponents = $rows->map(fn ($row) => $targetFirst ? ($resolved[$row->id][2] ?? $row->player2) : ($resolved[$row->id][1] ?? $row->player1))->filter()->sortBy('id')->map(fn ($opponent) => trim($opponent->name.' '.$opponent->surname))->implode(' / ');
            yield $dateEvidence + ['outcome_target' => app(PlayedMatchMarginPolicy::class)->target($fixture->teamResults->sortBy('set_nr')->map(fn ($set) => [(int) $set->team1_score, (int) $set->team2_score])->values()->all(), $winner), 'shared_cohort' => $sharedCohort, 'player1_id' => $ids1->first(), 'player2_id' => $ids2->first(), 'winner_side' => $winner, 'fixture_id' => $fixture->id, 'source' => 'Team match', 'event_id' => $event->id, 'event' => $event->name, 'date' => CarbonImmutable::parse($event->end_date)->toDateString(), 'category' => $category, 'cohort' => $cohort, 'tier' => 'Open', 'discipline' => $discipline, 'won' => $won, 'opponents' => $opponents, 'points' => $reason ? null : ($won ? 100 : 0), 'reason' => $reason, 'score' => $fixture->teamResults->map(fn ($set) => $targetFirst ? $set->team1_score.'-'.$set->team2_score : $set->team2_score.'-'.$set->team1_score)->implode(' ')];
        }
    }

    private function completeSequence(Collection $sets, int $needed, string $first, string $second): bool
    {
        $wins = [0, 0];
        foreach ($sets->sortBy('set_nr') as $set) {
            if (max($wins) >= $needed) { return false; }
            $wins[$set->$first > $set->$second ? 0 : 1]++;
        }
        return max($wins) === $needed;
    }

    private function verifiedTeamCohort(array $sources, Event $event, string $drawName): ?string
    {
        if (count($sources) !== 2) { return null; }
        $labels = [];
        foreach ($sources as $source) {
            if ($source->category && (int) $source->category->event_id !== (int) $event->id) { return null; }
            $label = $source->category?->category?->name ?? $source->name;
            if (!$source->category && $source->regions?->region_name) {
                // A complete token prefix of the exact proven region display name is presentation, never a strength modifier.
                if (preg_match('/^(.+?)\s+(u\s*\/?\s*\d{1,2}\b.*)$/iu', trim($label ?? ''), $parts)
                    && preg_match('/^'.preg_quote(trim($parts[1]), '/').'(?:\s|$)/iu', trim($source->regions->region_name))) {
                    $label = $parts[2];
                }
            }
            $division = app(PlayerPerformancePilotService::class)->division($label ?? '');
            if ($division['reason'] && $division['reason'] !== 'No explicit A/B division in category name') { return null; }
            $cohort = app(PlayerSharedAbilityService::class)->cohort($division['cohort']);
            if (!preg_match('/^u\d{1,2} (boys|girls)(?: |$)/u', $cohort)
                || preg_match_all('/\bu\d{1,2}\b/u', $cohort) !== 1 || preg_match_all('/\b(boys|girls)\b/u', $cohort) !== 1 || str_contains($cohort, 'mixed')) { return null; }
            $labels[] = $cohort;
        }
        $drawLabel = app(PlayerSharedAbilityService::class)->cohort(app(PlayerPerformancePilotService::class)->division($drawName)['cohort']);
        if (preg_match('/^(u\d{1,2}) (boys|girls)(?: |$)/u', $drawLabel, $drawParts)) {
            if (!str_starts_with($labels[0], $drawParts[1].' '.$drawParts[2])) { return null; }
        }
        return $labels[0] === $labels[1] ? ($event->frontend_type_view === 'masters' ? 'masters · '.$labels[0] : $labels[0]) : null;
    }

    private function legacyTerminalSet(int $first, int $second): bool
    {
        $high = max($first, $second); $low = min($first, $second);
        return ($high === 4 && $low <= 3) || ($high === 5 && $low === 3)
            || ($high === 6 && $low <= 4) || ($high === 7 && in_array($low, [5,6], true))
            || ($high === 8 && $low <= 6) || ($high === 9 && in_array($low, [7,8], true))
            || ($high >= 10 && $high - $low >= 2);
    }
}
