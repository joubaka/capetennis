<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\TeamTie;

final class TeamStandingsService
{
    public function tieOutcome(TeamTie $tie): array
    {
        $result = ['complete' => $tie->rubbers->isNotEmpty() && app(TeamTieValidationService::class)->requiredRubbersPresent($tie), 'home' => 0, 'away' => 0, 'winner_team_id' => null];
        foreach ($tie->rubbers as $rubber) {
            $outcome = app(TeamRubberResultService::class)->outcome($rubber);
            $result['complete'] = $result['complete'] && $outcome['complete'];
            if ($outcome['complete'] && $outcome['winner']) { $result[$outcome['winner']]++; }
        }
        if ($result['complete'] && $result['home'] !== $result['away']) {
            $result['winner_team_id'] = $result['home'] > $result['away'] ? $tie->home_team_id : $tie->away_team_id;
        }
        return $result;
    }

    public function refreshTie(TeamTie $tie): void
    {
        $tie->load('rubbers.teamResults', 'rubbers.draw');
        $outcome = $this->tieOutcome($tie);
        $tie->update(['winner_team_id' => $outcome['winner_team_id'], 'status' => $outcome['complete']
            ? TeamTie::STATUS_COMPLETED
            : ($tie->published_at ? TeamTie::STATUS_PUBLISHED : ($tie->status === TeamTie::STATUS_COMPLETED ? TeamTie::STATUS_VALIDATED : $tie->status))]);
    }

    public function forDraw(Draw $draw, bool $publishedOnly = false): array
    {
        $rows = [];
        $rules = app(TeamEventRulesService::class)->forDraw($draw);
        $results = app(TeamRubberResultService::class);
        $ties = $draw->teamTies()->when($publishedOnly, fn ($query) => $query->whereNotNull('published_at')->whereIn('status', [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED]))->with(['homeTeam', 'awayTeam', 'rubbers.teamResults', 'rubbers.draw'])->get();
        foreach ($ties as $tie) {
            foreach (['home', 'away'] as $side) {
                $team = $tie->{$side.'Team'};
                if (!$team) { continue; }
                $rows[$team->id] ??= ['team_id' => $team->id, 'name' => $team->name, 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'points' => 0, 'rubber_wins' => 0, 'rubber_losses' => 0, 'sets_for' => 0, 'sets_against' => 0, 'games_for' => 0, 'games_against' => 0];
            }
            if (!isset($rows[$tie->home_team_id], $rows[$tie->away_team_id])) { continue; }
            foreach ($tie->rubbers as $rubber) {
                $outcome = $results->outcome($rubber);
                // Only completed rubbers contribute standings, never provisional sets.
                if (!$outcome['complete']) { continue; }
                $points = $results->points($rubber, $outcome);
                foreach (['home' => $tie->home_team_id, 'away' => $tie->away_team_id] as $side => $id) {
                    $other = $side === 'home' ? 'away' : 'home';
                    $rows[$id]['points'] += $points[$side];
                    $rows[$id]['rubber_wins'] += $outcome['winner'] === $side ? 1 : 0;
                    $rows[$id]['rubber_losses'] += $outcome['winner'] === $other ? 1 : 0;
                    $rows[$id]['sets_for'] += $outcome[$side.'_sets'];
                    $rows[$id]['sets_against'] += $outcome[$other.'_sets'];
                    $rows[$id]['games_for'] += $outcome[$side.'_games'];
                    $rows[$id]['games_against'] += $outcome[$other.'_games'];
                }
            }
            $outcome = $this->tieOutcome($tie);
            if (!$outcome['complete']) { continue; }
            foreach ([$tie->home_team_id, $tie->away_team_id] as $id) {
                $rows[$id]['played']++;
                $state = !$outcome['winner_team_id'] ? 'draw' : ($id === $outcome['winner_team_id'] ? 'win' : 'loss');
                $rows[$id][['win' => 'wins', 'draw' => 'draws', 'loss' => 'losses'][$state]]++;
                $rows[$id]['points'] += $rules['tie_'.$state];
            }
        }
        $criteria = fn ($row) => array_map(fn ($key) => match ($key) {
            'tie_wins' => $row['wins'], 'rubber_difference' => $row['rubber_wins'] - $row['rubber_losses'],
            'set_difference' => $row['sets_for'] - $row['sets_against'], 'game_difference' => $row['games_for'] - $row['games_against'], default => $row['points'],
        }, $rules['standings_order']);
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => ($criteria($b) <=> $criteria($a)) ?: ($a['team_id'] <=> $b['team_id']));
        $previous = null;
        $rank = 0;
        foreach ($rows as $index => &$row) {
            $current = $criteria($row);
            if ($current !== $previous) { $rank = $index + 1; }
            $row['rank'] = $rank;
            $previous = $current;
        }
        unset($row);
        return $rows;
    }
}
