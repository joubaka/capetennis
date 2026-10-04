<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\TeamFixture;
use Illuminate\Validation\ValidationException;

final class TeamRubberResultService
{
    public function rules(TeamFixture $fixture): array
    {
        $rules = app(TeamEventRulesService::class)->forDraw($fixture->draw);
        $code = $fixture->rubber_code ?? RubberType::fromLegacyFixtureType($fixture->fixture_type) ?? RubberType::SINGLES;
        return $rules['rubbers'][$code];
    }

    public function validate(TeamFixture $fixture, array $scores): array
    {
        $wins = ['home' => 0, 'away' => 0];
        $needed = $this->rules($fixture)['sets_to_win'];
        $gap = false;
        $sets = [];
        foreach (range(1, 3) as $number) {
            $home = $scores["set{$number}_home"] ?? null;
            $away = $scores["set{$number}_away"] ?? null;
            if ($home === '' ) { $home = null; }
            if ($away === '' ) { $away = null; }
            if ($home === null && $away === null) { $gap = true; continue; }
            $validInteger = fn ($value) => is_int($value) || (is_string($value) && preg_match('/^\d+$/D', $value));
            if ($gap || !$validInteger($home) || !$validInteger($away) || $home < 0 || $away < 0 || $home > 99 || $away > 99 || (int) $home === (int) $away || max($wins) >= $needed) {
                throw ValidationException::withMessages(["set{$number}_home" => 'Enter consecutive completed sets with both scores, no tied sets, and no sets after the match is won.']);
            }
            $sets[] = ['set_nr' => $number, 'team1_score' => (int) $home, 'team2_score' => (int) $away];
            $wins[$home > $away ? 'home' : 'away']++;
        }
        return $sets;
    }

    public function outcome(TeamFixture $fixture): array
    {
        $result = ['winner' => null, 'home_sets' => 0, 'away_sets' => 0, 'home_games' => 0, 'away_games' => 0, 'complete' => false];
        foreach ($fixture->teamResults->sortBy('set_nr')->unique('set_nr') as $set) {
            if (!is_numeric($set->team1_score) || !is_numeric($set->team2_score)) { continue; }
            $home = (int) $set->team1_score;
            $away = (int) $set->team2_score;
            $result['home_games'] += $home;
            $result['away_games'] += $away;
            if ($home !== $away) { $result[$home > $away ? 'home_sets' : 'away_sets']++; }
        }
        $newRules = $fixture->draw->team_scoring_rules !== null;
        $needed = $newRules ? $this->rules($fixture)['sets_to_win'] : max(1, (int) ceil(($fixture->numSets ?: ($fixture->isSingles() ? 3 : 1)) / 2));
        $result['complete'] = max($result['home_sets'], $result['away_sets']) >= $needed;
        if ($result['home_sets'] !== $result['away_sets'] && (!$newRules || $result['complete'])) {
            $result['winner'] = $result['home_sets'] > $result['away_sets'] ? 'home' : 'away';
        }
        return $result;
    }

    public function points(TeamFixture $fixture, array $outcome): array
    {
        if (!$outcome['complete'] || !$outcome['winner']) { return ['home' => 0, 'away' => 0]; }
        $rules = $this->rules($fixture);
        $winner = $outcome['winner'];
        $loser = $winner === 'home' ? 'away' : 'home';
        $deciding = $outcome[$loser.'_sets'] === $rules['sets_to_win'] - 1 && $rules['sets_to_win'] > 1;
        $loserPoints = $deciding ? $rules['deciding_loss'] : $rules['loss'];
        if (isset($rules['close_loss'], $rules['close_game_margin']) && abs($outcome['home_games'] - $outcome['away_games']) === $rules['close_game_margin']) {
            $loserPoints = max($loserPoints, $rules['close_loss']);
        }
        return [$winner => $deciding ? $rules['deciding_win'] : $rules['straight_win'], $loser => $loserPoints];
    }
}
