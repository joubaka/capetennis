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
                        ->orWhereHas('fixtures', fn ($fixtures) => $fixtures->whereHas('fixturePlayers', fn ($players) => $players->where('team1_id', $player->id)->orWhere('team2_id', $player->id))));
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

    public function individualMatches(Event $event, ?Player $player, PlayerPerformancePilotService $pilot): \Generator
    {
        if (!$event->published) { return; }
        $cache = [];
        $definitions = []; $memberships = [];
        $query = Fixture::query()->with(['draw.categoryEvent.category', 'draw.settings', 'registration1.players', 'registration2.players', 'fixtureResults'])
            ->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id)->where('published', true))
            ->when(!$player, fn ($query) => $query->whereIn('match_status', [1,2,3])->whereHas('fixtureResults'))
            ->when($player, fn ($query) => $query->where(fn ($query) => $query->whereHas('registration1.players', fn ($players) => $players->whereKey($player->id))->orWhereHas('registration2.players', fn ($players) => $players->whereKey($player->id))));
        foreach ($query->lazyById(50) as $fixture) {
            $first = $fixture->registration1?->players ?? collect();
            $second = $fixture->registration2?->players ?? collect();
            $discipline = $first->count() === 2 && $second->count() === 2 ? 'doubles' : 'singles';
            $field = $fixture->draw->categoryEvent;
            if (!$field || (int) $field->event_id !== (int) $event->id) {
                if ($field) {
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
            $winsNeeded = (int) ceil(max(1, min(5, (int) ($fixture->draw->settings?->num_sets ?: 3))) / 2);
            $maxWins = max($sets->filter(fn ($set) => $set->registration1_score > $set->registration2_score)->count(), $sets->filter(fn ($set) => $set->registration2_score > $set->registration1_score)->count());
            if (!in_array((int) $fixture->match_status, [1, 2, 3], true) || !$expected || !$validation['valid'] || (!$hasPreset && !$credibleLegacy) || ($declared && $declared !== $expected)
                || ((int) $fixture->match_status === 2 && (!$legacyRr || (!$hasPreset && $maxWins < $winsNeeded)))
                || ((int) $fixture->match_status === 3 && ($declared !== $expected || (!$hasPreset && $maxWins < $winsNeeded)))) {
                $reason = 'Match is unfinished, unplayed or has inconsistent winner/score evidence';
            }
            $targetFirst = !$player || $first->contains('id', $player->id);
            $cohort = $division['cohort'];
            if ($event->frontend_type_view === 'masters') { $cohort = 'masters · '.$cohort; }
            $won = $winner === ($targetFirst ? 1 : 2);
            $tier = $division['tier'];
            $opponents = ($targetFirst ? $second : $first)->map(fn ($opponent) => trim($opponent->name.' '.$opponent->surname))->implode(' / ');
            yield ['fixture_id' => $fixture->id, 'source' => 'Individual match', 'event_id' => $event->id, 'event' => $event->name, 'date' => CarbonImmutable::parse($event->end_date)->toDateString(), 'category' => $field?->category?->name ?? $fixture->draw->drawName, 'cohort' => $cohort, 'tier' => $tier, 'discipline' => $discipline, 'won' => $won, 'player1_id' => $first->first()?->id, 'player2_id' => $second->first()?->id, 'winner_side' => $winner, 'opponents' => $opponents, 'points' => $reason ? null : ($tier === 'A' ? 50 : 0) + ($tier === 'Open' ? 100 : 50) * (int) $won, 'reason' => $reason, 'score' => $sets->map(fn ($set) => $targetFirst ? $set->registration1_score.'-'.$set->registration2_score : $set->registration2_score.'-'.$set->registration1_score)->implode(' ')];
        }
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

    private function teamMatches(Event $event, Player $player): \Generator
    {
        if (!$event->published) { return; }
        $query = TeamFixture::query()->publishedTeamTies()->with(['draw.categoryEvent.category', 'teamTie.homeTeam.category', 'teamTie.awayTeam.category', 'fixturePlayers', 'teamResults'])
            ->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id)->where('published', true))
            ->whereHas('fixturePlayers', fn ($players) => $players->where('team1_id', $player->id)->orWhere('team2_id', $player->id));
        foreach ($query->lazyById(50) as $fixture) {
            $discipline = $fixture->isDoubles() ? 'doubles' : 'singles';
            $needed = $discipline === 'singles' ? 1 : 2;
            $rows = $fixture->fixturePlayers;
            $ids1 = $rows->pluck('team1_id'); $ids2 = $rows->pluck('team2_id');
            $reason = null;
            if ($fixture->teamTie && (int) $fixture->teamTie->draw_id !== (int) $fixture->draw_id) { $reason = 'Team tie does not belong to this draw'; }
            if ($rows->count() !== $needed || $ids1->filter()->count() !== $needed || $ids2->filter()->count() !== $needed || $ids1->merge($ids2)->unique()->count() !== 2 * $needed || $rows->contains(fn ($row) => $row->team1_no_profile_id || $row->team2_no_profile_id)) { $reason = 'Team match has imported, missing or ambiguous player identities'; }
            foreach ($rows as $row) {
                foreach ([1,2] as $side) {
                    $snapshot = $row->participant_snapshot[$side] ?? null;
                    $source = $fixture->teamTie?->{($side === 1 ? 'homeTeam' : 'awayTeam')};
                    if (!$fixture->teamTie && $fixture->{'region'.$side} && $fixture->draw->category_event_id) {
                        $legacySources = \App\Models\Team::query()->with('category')
                            ->where('region_id', $fixture->{'region'.$side})
                            ->where('category_event_id', $fixture->draw->category_event_id)
                            ->whereHas('category', fn ($category) => $category->where('event_id', $event->id));
                        foreach (($side === 1 ? $ids1 : $ids2) as $profileId) {
                            $legacySources->whereHas('team_players', fn ($members) => $members->where('player_id', $profileId));
                        }
                        $matches = $legacySources->limit(2)->get();
                        $source = $matches->count() === 1 ? $matches->first() : null;
                    }
                    $map = $fixture->draw->team_draw_selection['mixed_sides'][$source?->id] ?? null;
                    $allowed = $map ? [(int) $map['boys'], (int) $map['girls']] : [(int) $source?->id];
                    if ($snapshot) {
                        $historySource = \App\Models\Team::with('category')->find($snapshot['source_team_id'] ?? 0);
                        if (!$historySource || !in_array((int) $historySource->id, $allowed, true)
                            || (int) $historySource->category?->event_id !== (int) $event->id
                            || !app(\App\Services\TeamParticipantHistoryService::class)->matches($row, $side, $historySource, (int) $event->id)) {
                            $reason = 'Team match participant history does not match this event/player/side';
                        }
                    } else {
                        $validMember = \App\Models\Team::query()->whereIn('id', $allowed)
                            ->whereHas('category', fn ($category) => $category->where('event_id', $event->id))
                            ->whereHas('team_players', fn ($members) => $members->where('player_id', $row->{'team'.$side.'_id'}))->exists();
                        if (!$validMember) { $reason = 'Team match player cannot be verified against this event/side'; }
                    }
                }
            }
            $winner = $this->completedWinner($fixture->teamResults, 'team1_score', 'team2_score');
            $outcome = app(TeamRubberResultService::class)->outcome($fixture);
            $terminal = $fixture->teamResults->every(fn ($set) => $this->legacyTerminalSet((int) $set->team1_score, (int) $set->team2_score));
            if ((int) $fixture->match_status !== 1 || !$winner || !$terminal || !$outcome['complete'] || $outcome['winner'] !== ($winner === 1 ? 'home' : 'away')) { $reason = 'Team match is incomplete or has inconsistent score evidence'; }
            $field = $fixture->draw->categoryEvent;
            $category = $field?->category?->name ?? $fixture->draw->drawName;
            if (!$field || (int) $field->event_id !== (int) $event->id) { $reason = 'Team match category/event cannot be verified'; }
            $cohort = 'team · '.mb_strtolower(trim(preg_replace('/\s+/u', ' ', $category)));
            $won = $winner === ($ids1->contains($player->id) ? 1 : 2);
            $targetFirst = $ids1->contains($player->id);
            $opponents = Player::query()->whereIn('id', $targetFirst ? $ids2 : $ids1)->orderBy('id')->get(['name', 'surname'])->map(fn ($opponent) => trim($opponent->name.' '.$opponent->surname))->implode(' / ');
            yield ['fixture_id' => $fixture->id, 'source' => 'Team match', 'event_id' => $event->id, 'event' => $event->name, 'date' => CarbonImmutable::parse($event->end_date)->toDateString(), 'category' => $category, 'cohort' => $cohort, 'tier' => 'Open', 'discipline' => $discipline, 'won' => $won, 'opponents' => $opponents, 'points' => $reason ? null : ($won ? 100 : 0), 'reason' => $reason, 'score' => $fixture->teamResults->map(fn ($set) => $targetFirst ? $set->team1_score.'-'.$set->team2_score : $set->team2_score.'-'.$set->team1_score)->implode(' ')];
        }
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
