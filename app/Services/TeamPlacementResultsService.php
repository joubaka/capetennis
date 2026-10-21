<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\TeamTie;

/** Placement encounters determine places independently of league points. */
final class TeamPlacementResultsService
{
    public function forDraw(Draw $draw, bool $publishedOnly = false, ?array $teamIds = null): array
    {
        if (($draw->team_draw_selection['competition']['phase'] ?? null) !== 'playoff'
            || ($draw->team_scoring_rules['undecided'] ?? false)
            || ($publishedOnly && !$draw->published)) {
            return [];
        }

        $ties = $draw->teamTies()
            ->when($publishedOnly, fn ($query) => $query->whereNotNull('published_at')
                ->whereIn('status', [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED]))
            ->with(['homeTeam.category', 'awayTeam.category', 'rubbers.teamResults', 'rubbers.draw'])
            ->orderBy('tie_nr')->get();
        $results = [];
        foreach ($ties as $tie) {
            if (!in_array((int) $tie->tie_nr, [1, 2], true)
                || !$tie->homeTeam || !$tie->awayTeam
                || (int) $tie->homeTeam->category?->event_id !== (int) $draw->event_id
                || (int) $tie->awayTeam->category?->event_id !== (int) $draw->event_id
                || ($teamIds !== null && (!in_array($tie->home_team_id, $teamIds, true) || !in_array($tie->away_team_id, $teamIds, true)))
                || $tie->rubbers->contains(fn ($rubber) => (int) $rubber->draw_id !== (int) $draw->id)) {
                continue;
            }
            $tie->setRelation('draw', $draw);
            $outcome = app(TeamStandingsService::class)->tieOutcome($tie);
            $home = app(TeamDrawSideResolver::class)->label($draw, $tie->homeTeam);
            $away = app(TeamDrawSideResolver::class)->label($draw, $tie->awayTeam);
            $position = (int) $tie->tie_nr === 1 ? 1 : 3;
            $winner = $outcome['winner_team_id'];
            $results[] = [
                'tie_id' => $tie->id,
                'position' => $position,
                'label' => $position === 1 ? '1st / 2nd' : '3rd / 4th',
                'home_name' => $home, 'away_name' => $away,
                'home_wins' => $outcome['home'], 'away_wins' => $outcome['away'],
                'complete' => $outcome['complete'],
                'decision_method' => $outcome['decision_method'] ?? null,
                'deciding_score' => $outcome['deciding_score'] ?? null,
                'winner_name' => $winner ? ($winner === $tie->home_team_id ? $home : $away) : null,
                'loser_name' => $winner ? ($winner === $tie->home_team_id ? $away : $home) : null,
            ];
        }

        $automaticThird = $draw->team_draw_selection['competition']['automatic_third_team_id'] ?? null;
        if (!$automaticThird && $draw->teams_in_draw()->count() === 3) {
            $pairedIds = $draw->teamTies()->get()->flatMap(fn ($tie) => [$tie->home_team_id, $tie->away_team_id])->unique()->values()->all();
            $unpaired = $draw->teams_in_draw()->whereNotIn('teams.id', $pairedIds)->pluck('teams.id');
            if (count($pairedIds) === 2 && $unpaired->count() === 1) $automaticThird = $unpaired->first();
        }
        if ($automaticThird && ($teamIds === null || in_array((int) $automaticThird, $teamIds, true))) {
            $team = $draw->teams_in_draw()->where('teams.id', $automaticThird)->whereHas('category', fn ($q) => $q->where('event_id', $draw->event_id))->first();
            if ($team) $results[] = ['position' => 3, 'label' => '3rd place', 'automatic' => true,
                'winner_name' => app(TeamDrawSideResolver::class)->label($draw, $team), 'loser_name' => null,
                'home_name' => '', 'away_name' => '', 'home_wins' => 0, 'away_wins' => 0, 'complete' => true];
        }
        return $results;
    }
}
