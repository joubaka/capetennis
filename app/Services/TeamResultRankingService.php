<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFixture;
use Illuminate\Support\Collection;

/** Private, event-scoped selection evidence. Does not publish or select players. */
class TeamResultRankingService
{
    public function fixtures(Event $event): Collection
    {
        return TeamFixture::without(['teamResults', 'fixturePlayers', 'draw'])
            ->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))
            ->with(['draw.categoryEvent.category'])->get()->filter(fn ($fixture) => in_array($this->format($fixture), [RubberType::SINGLES, RubberType::REVERSE_SINGLES], true));
    }

    public function group(TeamFixture $fixture): ?array
    {
        $labels = [$fixture->age, $fixture->draw?->categoryEvent?->category?->name, $fixture->draw?->drawName];
        $age = null;
        $gender = null;
        foreach ($labels as $label) {
            if (! $age && preg_match('/(?:u\s*\/?\s*|under\s*)(\d{1,2})\b/i', (string) $label, $match)) $age = (int) $match[1];
            if (! $gender && preg_match('/\b(boys?|girls?|male|female)\b/i', (string) $label, $match)) {
                $gender = in_array(strtolower($match[1]), ['boy', 'boys', 'male']) ? 'boys' : 'girls';
            }
        }
        $rule = strtolower((string) ($fixture->gender_rule ?: $fixture->draw?->gender));
        if (in_array($rule, ['male', 'boys', 'boy', 'm'])) $gender = 'boys';
        if (in_array($rule, ['female', 'girls', 'girl', 'f'])) $gender = 'girls';
        if (! $age && ctype_digit((string) $fixture->age)) $age = (int) $fixture->age;
        return $age && $gender ? ['key' => $age.'-'.$gender, 'name' => 'u/'.$age.' '.ucfirst($gender), 'age' => $age] : null;
    }

    public function format(TeamFixture $fixture): ?string
    {
        if ($fixture->rubber_code === null && $fixture->fixture_type === null && $fixture->draw?->category_event_id === null) {
            $name = (string) $fixture->draw?->drawName;
            if (preg_match('/\bsingles\b/i', $name) && ! preg_match('/\b(?:doubles|mixed)\b/i', $name)) {
                return preg_match('/\breverse\b/i', $name) ? RubberType::REVERSE_SINGLES : RubberType::SINGLES;
            }
        }
        return $fixture->rubber_code ?? RubberType::fromLegacyFixtureType($fixture->fixture_type);
    }

    public function setup(Event $event): array
    {
        $fixtures = $this->fixtures($event);
        return [
            'groups' => $fixtures->map(fn ($fixture) => $this->group($fixture))->filter()->unique('key')->sortBy('age')->values(),
            'formats' => $fixtures->map(fn ($fixture) => $this->format($fixture))->unique()->values(),
            'regions' => $event->regions()->get(),
        ];
    }

    public function ranking(Event $event, string $group, array $regions, array $formats, array $excludedResultRegionIds = [], bool $includeRatings = false): Collection
    {
        return $this->selectionEvidence($event, $group, $regions, $formats, $excludedResultRegionIds, $includeRatings)['ranking'];
    }

    public function selectionEvidence(Event $event, string $group, array $regions, array $formats, array $excludedResultRegionIds = [], bool $includeRatings = false): array
    {
        $excludedResultRegionIds = array_map('intval', $excludedResultRegionIds);
        $fixtures = $this->fixtures($event)->filter(fn ($fixture) => ($this->group($fixture)['key'] ?? null) === $group && in_array($this->format($fixture), $formats, true));
        $fixtures->load(['fixturePlayers.player1', 'fixturePlayers.player2', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'teamResults', 'teamTie']);
        $teams = app(EventTeamScope::class)->query($event)->with(['players', 'team_players', 'team_players_no_profile', 'competitionSubstitutions', 'regions', 'category.category'])->get();
        $rows = [];
        $performance = [];
        $regionSummary = $event->regions()->whereIn('team_regions.id', $regions)
            ->whereNotIn('team_regions.id', $excludedResultRegionIds)->get()->mapWithKeys(fn ($region) => [
                (int) $region->id => ['region_id' => (int) $region->id, 'name' => $region->region_name,
                    'wins' => 0, 'losses' => 0, 'played' => 0, 'win_percentage' => null],
            ])->all();
        foreach ($fixtures as $fixture) {
            if (array_intersect(array_map('intval', [$fixture->region1, $fixture->region2]), $excludedResultRegionIds)) continue;
            if ($fixture->team_tie_id && (! $fixture->teamTie || (int) $fixture->teamTie->draw_id !== (int) $fixture->draw_id || ! $fixture->teamTie->home_team_id || ! $fixture->teamTie->away_team_id)) continue;
            // Project missing legacy format only for this private read. Without
            // a set-count rule, require two sets for a completed singles match.
            $resultFixture = $fixture;
            if ($fixture->rubber_code === null && $fixture->fixture_type === null) {
                $resultFixture = clone $fixture;
                $resultFixture->rubber_code = $this->format($fixture);
                $resultFixture->numSets ??= 3;
            }
            $outcome = app(TeamRubberResultService::class)->outcome($resultFixture);
            if (! $outcome['complete'] || ! $outcome['winner'] || $fixture->fixturePlayers->count() !== 1) continue;
            $slot = $fixture->fixturePlayers->first();
            $sides = [];
            foreach (['home', 'away'] as $side) {
                $player = $side === 'home' ? $slot->player1 : $slot->player2;
                $imported = $side === 'home' ? $slot->noProfile1 : $slot->noProfile2;
                if ($player && $imported) continue;
                $player ??= $imported;
                $region = $side === 'home' ? $fixture->region1 : $fixture->region2;
                $teamId = $side === 'home' ? $fixture->teamTie?->home_team_id : $fixture->teamTie?->away_team_id;
                $sideNumber = $side === 'home' ? 1 : 2;
                $mixed = $fixture->draw->team_draw_selection['mixed_sides'][$teamId] ?? null;
                $allowedTeams = $mixed ? array_map('intval', [$mixed['boys'], $mixed['girls']]) : ($teamId ? [(int) $teamId] : []);
                $snapshot = $slot->participant_snapshot[$sideNumber] ?? null;
                $candidateTeams = $teams->filter(fn ($team) => ! $teamId || in_array((int) $team->id, $allowedTeams, true));
                if (! $snapshot) $candidateTeams = $candidateTeams->map(fn ($team) => app(TeamDrawSideResolver::class)->activeRoster($team, (int) $fixture->round_nr, (int) $fixture->id, (int) $fixture->draw_id));
                $candidates = $candidateTeams->filter(function ($team) use ($fixture, $player, $imported, $region, $teamId, $allowedTeams, $slot, $sideNumber, $snapshot, $event) {
                    if (! $player || ($teamId && ! in_array((int) $team->id, $allowedTeams, true))) return false;
                    if ($region && (int) $team->region_id !== (int) $region) return false;
                    if (! $teamId && $fixture->draw->category_event_id && (int) $team->category_event_id !== (int) $fixture->draw->category_event_id) return false;
                    if (! $teamId && ! $fixture->draw->category_event_id) {
                        if ($team->category_event_id) {
                            if ($team->category?->category?->name !== $fixture->draw->drawName) return false;
                        } else {
                            $rosterFixture = new TeamFixture(['age' => $team->name]);
                            if (($this->group($rosterFixture)['key'] ?? null) !== ($this->group($fixture)['key'] ?? null)) return false;
                        }
                    }
                    return $snapshot ? app(TeamParticipantHistoryService::class)->matches($slot, $sideNumber, $team, (int) $event->id) : ($imported ? $team->team_players_no_profile->contains('id', $imported->id) : $team->team_players->contains('player_id', $player->id));
                });
                // Ambiguous roster membership must not silently supply a rank.
                if ($candidates->count() !== 1) continue;
                $team = $candidates->first();
                $member = $imported ? $team->team_players_no_profile->firstWhere('id', $imported->id) : $team->team_players->firstWhere('player_id', $player->id);
                $rank = $snapshot ? (int) ($snapshot['rank'] ?? 0) : (int) $member->rank;
                if ($rank < 1) continue;
                $identity = $imported ? 'imported:'.$team->id.':'.($snapshot['anchor_id'] ?? $member->competition_anchor_id ?? $imported->id) : $player->id;
                $sides[$side] = compact('player', 'team', 'rank', 'identity');
            }
            if (count($sides) !== 2) continue;
            if (collect($sides)->contains(fn ($entry) => in_array((int) $entry['team']->region_id, $excludedResultRegionIds, true))) continue;
            foreach ($sides as $side => $entry) {
                if (! in_array((int) $entry['team']->region_id, $regions, true)) continue;
                $id = $entry['identity'];
                $opponent = $sides[$side === 'home' ? 'away' : 'home'];
                $won = $outcome['winner'] === $side;
                // Count resolved match sides before player identities merge across teams.
                $regionId = (int) $entry['team']->region_id;
                if (isset($regionSummary[$regionId])) {
                    $regionSummary[$regionId][$won ? 'wins' : 'losses']++;
                    $regionSummary[$regionId]['played']++;
                }
                $rows[$id] ??= ['id' => $id, 'name' => $entry['player']->name.' '.$entry['player']->surname, 'rank' => $entry['rank'], 'ranks' => [], 'teams' => [], 'region' => $entry['team']->regions?->short_name ?? '', 'wins' => 0, 'losses' => 0, 'singles_wins' => 0, 'reverse_singles_wins' => 0, 'sets_won' => 0, 'sets_lost' => 0, 'cross_band_review' => [], 'higher_rank_wins' => 0, 'matches' => []];
                if ($includeRatings) $rows[$id]['rating_player_id'] = is_numeric($id) ? (int) $id : ($entry['player']->player_profile ? (int) $entry['player']->player_profile : null);
                $rows[$id]['ranks'][] = $entry['rank'];
                $rows[$id]['teams'][] = $entry['team']->name;
                $rows[$id]['source_team_ids'][] = (int) $entry['team']->id;
                $rows[$id][$won ? 'wins' : 'losses']++;
                if ($won) $rows[$id][$this->format($fixture) === RubberType::SINGLES ? 'singles_wins' : 'reverse_singles_wins']++;
                $rows[$id]['sets_won'] += $outcome[$side.'_sets'];
                $rows[$id]['sets_lost'] += $outcome[($side === 'home' ? 'away' : 'home').'_sets'];
                $teamId = $entry['team']->id;
                $rank = $entry['rank'];
                $performance[$teamId][$rank][$id] ??= ['id' => $id, 'team' => $entry['team']->name, 'wins' => 0, 'losses' => 0];
                $performance[$teamId][$rank][$id][$won ? 'wins' : 'losses']++;
                if ($won && $opponent['rank'] < $entry['rank']) $rows[$id]['higher_rank_wins']++;
                $rows[$id]['matches'][] = ['opponent_id' => $opponent['identity'], 'opponent' => $opponent['player']->name.' '.$opponent['player']->surname, 'opponent_rank' => $opponent['rank'], 'roster_rank' => $entry['rank'], 'region' => $opponent['team']->regions?->short_name ?? '', 'won' => $won, 'sets_won' => $outcome[$side.'_sets'], 'sets_lost' => $outcome[($side === 'home' ? 'away' : 'home').'_sets'], 'format' => $this->format($fixture), 'score' => $fixture->teamResults->sortBy('set_nr')->map(fn ($set) => $set->team1_score.'–'.$set->team2_score)->implode(', ')];
            }
        }
        // Only each source team's unambiguous consecutive roster positions count.
        // Only completed match wins supply evidence of a winning record.
        foreach ($performance as $teamId => $ranks) {
            ksort($ranks);
            $identityRanks = [];
            foreach ($ranks as $rank => $players) foreach ($players as $player) $identityRanks[$player['id']][] = $rank;
            $runs = []; $run = []; $previousRank = null;
            foreach ($ranks as $rank => $players) {
                $player = count($players) === 1 ? reset($players) : null;
                $qualifies = $player && count($identityRanks[$player['id']]) === 1 && $player['wins'] + $player['losses'] >= 2 && $player['wins'] > $player['losses'];
                if (! $qualifies || ($previousRank !== null && $rank !== $previousRank + 1)) {
                    if ($run) $runs[] = $run;
                    $run = [];
                }
                if ($qualifies) $run[$rank] = $player;
                $previousRank = $rank;
            }
            if ($run) $runs[] = $run;
            foreach ($runs as $run) {
                if (count($run) < 4) continue;
                $context = ['source_team_id' => $teamId, 'team' => reset($run)['team'], 'ranks' => array_keys($run), 'records' => array_map(fn ($player) => ['wins' => $player['wins'], 'losses' => $player['losses']], $run)];
                foreach ($run as $player) $rows[$player['id']]['cross_band_review'][] = $context;
            }
        }
        $ranking = collect($rows)->map(function ($row) {
            $row['ranks'] = array_values(array_unique($row['ranks']));
            $row['teams'] = array_values(array_unique($row['teams']));
            $row['source_team_ids'] = array_values(array_unique($row['source_team_ids']));
            // Conservative weighting if historic rosters disagree; expose the discrepancy for review.
            $row['rank'] = max($row['ranks']);
            $row['points'] = $this->points($row['rank'], $row['wins']);
            $row['starting_credit'] = 0;
            $row['credited_wins'] = $row['wins'];
            $row['points_per_win'] = $this->points($row['rank'], 1);
            $row['band_index'] = (int) ceil($row['rank'] / 2);
            $row['band'] = (2 * (int) ceil($row['rank'] / 2) - 1).'–'.(2 * (int) ceil($row['rank'] / 2));
            $row['set_difference'] = $row['sets_won'] - $row['sets_lost'];
            return $row;
        });
        $ranking = $ranking->map(function ($row) use ($ranking) {
            $ownBandMatches = collect($row['matches'])->filter(fn ($match) => (int) ceil($match['opponent_rank'] / 2) === $row['band_index']
                && (int) ceil($match['roster_rank'] / 2) === $row['band_index']);
            $row['own_band_record'] = ['wins' => $ownBandMatches->where('won', true)->count(), 'losses' => $ownBandMatches->where('won', false)->count()];
            $row['band_attribution_uncertain'] = collect($row['ranks'])->map(fn ($rank) => (int) ceil($rank / 2))->unique()->count() > 1;
            $row['lost_all_counted_band_matches'] = ! $row['band_attribution_uncertain'] && $row['own_band_record']['wins'] === 0 && $row['own_band_record']['losses'] > 0;
            $row['adjacent_band_comparisons'] = [];
            foreach ([$row['band_index'] - 1, $row['band_index'] + 1] as $band) {
                if ($band < 1 || $band > 4) continue;
                $direct = collect($row['matches'])->filter(fn ($match) => (int) ceil($match['opponent_rank'] / 2) === $band
                    && (int) ceil($match['roster_rank'] / 2) === $row['band_index']);
                $opponents = $direct->groupBy(fn ($match) => (string) $match['opponent_id'])->map(fn ($matches) => [
                    'id' => $matches->first()['opponent_id'], 'name' => $matches->first()['opponent'],
                    'ranks' => $matches->pluck('opponent_rank')->unique()->values()->all(),
                    'wins' => $matches->where('won', true)->count(), 'losses' => $matches->where('won', false)->count(),
                    'sets_won' => $matches->sum('sets_won'), 'sets_lost' => $matches->sum('sets_lost'),
                ])->values()->all();
                $row['adjacent_band_comparisons'][] = ['band' => (2 * $band - 1).'–'.(2 * $band),
                    'direction' => $band < $row['band_index'] ? 'higher' : 'lower', 'direct_matches' => $direct->count(),
                    'direct_wins' => $direct->where('won', true)->count(), 'direct_losses' => $direct->where('won', false)->count(),
                    'sets_won' => $direct->sum('sets_won'), 'sets_lost' => $direct->sum('sets_lost'), 'opponents' => $opponents,
                    'candidate_records' => $ranking->where('band_index', $band)->map(fn ($other) => [
                        'id' => $other['id'], 'name' => $other['name'], 'ranks' => $other['ranks'], 'wins' => $other['wins'],
                        'losses' => $other['losses'], 'sets_won' => $other['sets_won'], 'sets_lost' => $other['sets_lost'],
                    ])->values()->all(),
                ];
            }
            return $row;
        });
        $ranking = $this->orderRanking($ranking);
        $ranking = $includeRatings ? app(TeamResultRatingService::class)->enrich($ranking, $event, $fixtures->isEmpty() ? null : $this->group($fixtures->first())) : $ranking;
        $regionSummary = collect($regionSummary)->map(function ($row) {
            $row['win_percentage'] = $row['played'] ? round(100 * $row['wins'] / $row['played'], 1) : null;
            return $row;
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        return compact('ranking', 'regionSummary');
    }

    public function orderRanking(Collection $ranking): Collection
    {
        $band = fn ($row) => (int) ceil($row['rank'] / 2);
        $ranking = $ranking->sort(fn ($a, $b) => ($band($a) <=> $band($b)) ?: ($b['points'] <=> $a['points']) ?: ($b['set_difference'] <=> $a['set_difference']))
            ->groupBy(fn ($row) => $band($row).':'.$row['points'].':'.$row['set_difference'])
            ->flatMap(function ($bucket, $scoreKey) {
                $source = fn ($row) => count($row['source_team_ids']) === 1 && count($row['ranks']) === 1 ? $row['source_team_ids'][0] : null;
                $singleTeam = $bucket->every(fn ($row) => $source($row) !== null)
                    && $bucket->map($source)->unique()->count() === 1;
                $distinctTeams = $bucket->every(fn ($row) => $source($row) !== null)
                    && $bucket->map($source)->unique()->count() === $bucket->count();
                $balanced = $distinctTeams && $bucket->count() > 1;
                $pairCount = null;
                foreach ($bucket as $row) {
                    foreach ($bucket as $opponent) {
                        if ((string) $row['id'] === (string) $opponent['id']) continue;
                        $matches = collect($row['matches'] ?? [])->filter(fn ($match) => (string) ($match['opponent_id'] ?? '') === (string) $opponent['id']);
                        $reverse = collect($opponent['matches'] ?? [])->filter(fn ($match) => (string) ($match['opponent_id'] ?? '') === (string) $row['id']);
                        $pairCount ??= $matches->count();
                        if ($matches->isEmpty() || $matches->count() !== $pairCount || $reverse->count() !== $pairCount
                            || $matches->where('won', true)->count() + $reverse->where('won', true)->count() !== $pairCount) $balanced = false;
                    }
                }
                $identities = $bucket->pluck('id')->map('strval')->all();
                $bucket = $bucket->map(function ($row) use ($identities, $balanced, $singleTeam) {
                    $direct = collect($row['matches'] ?? [])->filter(fn ($match) => in_array((string) ($match['opponent_id'] ?? ''), $identities, true));
                    $row['head_to_head'] = ['applied' => $balanced, 'wins' => $direct->where('won', true)->count(),
                        'tied_players' => count($identities),
                        'losses' => $direct->where('won', false)->count(),
                        'reason' => $singleTeam ? 'Same team: roster position decides.' : ($balanced ? 'Balanced direct results among tied players.' : 'Direct results are incomplete, unbalanced, or roster evidence is uncertain.')];
                    return $row;
                });
                // Sorting whole source-team blocks keeps the order transitive.
                // Mixed or ambiguous source evidence retains its shared tie.
                return $bucket->sort(function ($a, $b) use ($source, $balanced) {
                    $aSource = $source($a); $bSource = $source($b);
                    $blockOrder = ($aSource ?? PHP_INT_MAX) <=> ($bSource ?? PHP_INT_MAX);
                    return ($balanced ? ($b['head_to_head']['wins'] <=> $a['head_to_head']['wins']) : 0)
                        ?: $blockOrder ?: (($aSource !== null && $aSource === $bSource) ? ($a['rank'] <=> $b['rank']) : 0)
                        ?: strcasecmp($a['name'], $b['name']) ?: ((string) $a['id'] <=> (string) $b['id']);
                })->map(function ($row) use ($scoreKey, $singleTeam, $balanced) {
                    $row['_position_key'] = $scoreKey.($singleTeam ? ':rank:'.$row['rank'] : ($balanced ? ':h2h:'.$row['head_to_head']['wins'] : ''));
                    $row['same_team_tiebreak'] = $singleTeam;
                    return $row;
                })->values();
            })->values();
        // A pairwise same-team override can cycle against cross-team set differences.
        // Compare the highest remaining roster player from each team instead, using
        // the existing set-difference/direct-result order among eligible players.
        $ranking = $ranking->groupBy(fn ($row) => $band($row).':'.$row['points'])
            ->flatMap(function ($bucket) {
                $source = fn ($row) => count($row['source_team_ids']) === 1 && count($row['ranks']) === 1 ? $row['source_team_ids'][0] : null;
                $remaining = $bucket->values();
                $ordered = collect();
                while ($remaining->isNotEmpty()) {
                    $index = $remaining->search(function ($row) use ($remaining, $source) {
                        $team = $source($row);
                        return $team === null || ! $remaining->contains(fn ($other) => $source($other) === $team && $other['rank'] < $row['rank']);
                    });
                    $row = $remaining->get($index);
                    if ($source($row) !== null && $bucket->contains(fn ($other) => $source($other) === $source($row) && $other['rank'] !== $row['rank'])) {
                        $row['same_team_tiebreak'] = true;
                        $row['head_to_head']['reason'] = 'Same team: roster position takes precedence over set difference.';
                    }
                    $ordered->push($row);
                    $remaining->forget($index);
                }
                return $ordered;
            })->values();
        // Roster precedence can separate otherwise unresolved cross-team ties.
        // Every shared group spanning the selection boundary still needs review.
        $cutoffKeys = $ranking->take(10)->pluck('_position_key')->intersect($ranking->slice(10)->pluck('_position_key'))->all();
        $position = 0; $previous = null;
        return $ranking->map(function ($row, $index) use ($cutoffKeys, &$position, &$previous) {
            $key = $row['_position_key'];
            if ($previous !== $key) $position = $index + 1;
            $previous = $key;
            $row['position'] = $position;
            $row['cutoff_tie'] = in_array($key, $cutoffKeys, true);
            $row['suggested'] = $index < 10 && ! $row['cutoff_tie'];
            unset($row['_position_key']);
            return $row;
        });
    }

    public function points(int $rank, int $wins): int
    {
        $weight = match ($rank) { 1, 2 => 100, 3, 4 => 35, 5, 6 => 12, 7, 8 => 2, default => 0 };
        return $wins * $weight;
    }
}
