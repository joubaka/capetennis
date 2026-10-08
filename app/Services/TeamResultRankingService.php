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

    public function ranking(Event $event, string $group, array $regions, array $formats): Collection
    {
        $fixtures = $this->fixtures($event)->filter(fn ($fixture) => ($this->group($fixture)['key'] ?? null) === $group && in_array($this->format($fixture), $formats, true));
        $fixtures->load(['fixturePlayers.player1', 'fixturePlayers.player2', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'teamResults', 'teamTie']);
        $teams = app(EventTeamScope::class)->query($event)->with(['players', 'team_players', 'team_players_no_profile', 'competitionSubstitutions', 'regions', 'category.category'])->get();
        $rows = [];
        $performance = [];
        foreach ($fixtures as $fixture) {
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
            foreach ($sides as $side => $entry) {
                if (! in_array((int) $entry['team']->region_id, $regions, true)) continue;
                $id = $entry['identity'];
                $opponent = $sides[$side === 'home' ? 'away' : 'home'];
                $won = $outcome['winner'] === $side;
                $rows[$id] ??= ['id' => $id, 'name' => $entry['player']->name.' '.$entry['player']->surname, 'rank' => $entry['rank'], 'ranks' => [], 'teams' => [], 'region' => $entry['team']->regions?->short_name ?? '', 'wins' => 0, 'losses' => 0, 'singles_wins' => 0, 'reverse_singles_wins' => 0, 'sets_won' => 0, 'sets_lost' => 0, 'cross_band_review' => [], 'higher_rank_wins' => 0, 'matches' => []];
                $rows[$id]['ranks'][] = $entry['rank'];
                $rows[$id]['teams'][] = $entry['team']->name;
                $rows[$id][$won ? 'wins' : 'losses']++;
                if ($won) $rows[$id][$this->format($fixture) === RubberType::SINGLES ? 'singles_wins' : 'reverse_singles_wins']++;
                $rows[$id]['sets_won'] += $outcome[$side.'_sets'];
                $rows[$id]['sets_lost'] += $outcome[($side === 'home' ? 'away' : 'home').'_sets'];
                $teamId = $entry['team']->id;
                $rank = $entry['rank'];
                $performance[$teamId][$rank][$id] ??= ['id' => $id, 'team' => $entry['team']->name, 'wins' => 0, 'losses' => 0];
                $performance[$teamId][$rank][$id][$won ? 'wins' : 'losses']++;
                if ($won && $opponent['rank'] < $entry['rank']) $rows[$id]['higher_rank_wins']++;
                $rows[$id]['matches'][] = ['opponent' => $opponent['player']->name.' '.$opponent['player']->surname, 'opponent_rank' => $opponent['rank'], 'region' => $opponent['team']->regions?->short_name ?? '', 'won' => $won, 'format' => $this->format($fixture), 'score' => $fixture->teamResults->sortBy('set_nr')->map(fn ($set) => $set->team1_score.'–'.$set->team2_score)->implode(', ')];
            }
        }
        // Only each source team's unambiguous consecutive roster positions count.
        // Starting credits are never evidence of a winning record.
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
            // Conservative weighting if historic rosters disagree; expose the discrepancy for review.
            $row['rank'] = max($row['ranks']);
            $row['points'] = $this->points($row['rank'], $row['wins']);
            $row['starting_credit'] = $row['rank'] % 2 === 1 ? 1 : 0;
            $row['credited_wins'] = $row['wins'] + $row['starting_credit'];
            $row['band'] = (2 * (int) ceil($row['rank'] / 2) - 1).'–'.(2 * (int) ceil($row['rank'] / 2));
            $row['set_difference'] = $row['sets_won'] - $row['sets_lost'];
            return $row;
        })->sort(fn ($a, $b) => ($b['points'] <=> $a['points']) ?: ($b['set_difference'] <=> $a['set_difference']) ?: strcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']))->values();
        $cutoff = $ranking->get(9);
        $next = $ranking->get(10);
        $tieAtCutoff = $cutoff && $next && $cutoff['points'] === $next['points'] && $cutoff['set_difference'] === $next['set_difference'];
        $position = 0; $previous = null;
        return $ranking->map(function ($row, $index) use ($cutoff, $tieAtCutoff, &$position, &$previous) {
            $key = [$row['points'], $row['set_difference']];
            if ($previous !== $key) $position = $index + 1;
            $previous = $key;
            $row['position'] = $position;
            $row['cutoff_tie'] = $tieAtCutoff && $row['points'] === $cutoff['points'] && $row['set_difference'] === $cutoff['set_difference'];
            $row['suggested'] = $index < 10 && ! $row['cutoff_tie'];
            return $row;
        });
    }

    public function points(int $rank, int $wins): int
    {
        $weight = match ($rank) { 1 => 100, 2 => 50, 3, 4 => 35, 5, 6 => 12, 7, 8 => 2, default => 0 };
        return ($wins + ($rank % 2 === 1 ? 1 : 0)) * $weight;
    }
}
