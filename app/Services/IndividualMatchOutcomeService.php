<?php

namespace App\Services;

use App\Domain\Draws\Services\{ScoreValidationService, TennisScoreFormat};
use App\Models\Fixture;

final class IndividualMatchOutcomeService
{
    public function winner(Fixture $fixture): ?int
    {
        if ($fixture->isWalkover() || $fixture->isRetired()) return (int) $fixture->winner_registration;
        $sets = $fixture->fixtureResults->sortBy('set_nr')->unique('set_nr')
            ->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all();
        $decidingWinner = $fixture->draw?->settings?->score_format === TennisScoreFormat::TIMED
            && $fixture->stage !== 'RR' && count($sets) === 1 && (int) $sets[0][0] === (int) $sets[0][1]
            ? $fixture->winner_registration : null;
        return $this->winnerFromSets($fixture, $sets, $decidingWinner);
    }

    public function isDraw(Fixture $fixture): bool
    {
        if ($fixture->stage !== 'RR' || $fixture->isWalkover() || $fixture->isRetired()) return false;
        $sets = $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all();
        $homeWins = count(array_filter($sets, fn ($set) => $set[0] > $set[1]));
        $awayWins = count(array_filter($sets, fn ($set) => $set[1] > $set[0]));
        return $homeWins === $awayWins && app(ScoreValidationService::class)->validate($fixture, $sets)['valid'];
    }

    public function winnerFromSets(Fixture $fixture, array $sets, ?int $decidingPointWinnerId = null): ?int
    {
        if (!app(ScoreValidationService::class)->validate($fixture, $sets, $decidingPointWinnerId)['valid']) return null;
        $settings = $fixture->draw?->settings;
        $format = (string) ($settings?->score_format ?? '');
        if ($format === TennisScoreFormat::TIMED) {
            if ((int) $sets[0][0] === (int) $sets[0][1]) return $decidingPointWinnerId;
            return $sets[0][0] > $sets[0][1] ? $fixture->registration1_id : $fixture->registration2_id;
        }
        $needed = in_array($format, TennisScoreFormat::keys(), true) ? TennisScoreFormat::get($format)['wins_needed']
            : (int) ceil(max(1, min(5, (int) ($settings?->num_sets ?: 3))) / 2);
        $home = count(array_filter($sets, fn ($set) => $set[0] > $set[1]));
        $away = count(array_filter($sets, fn ($set) => $set[1] > $set[0]));
        if (max($home, $away) < $needed || $home === $away) return null;
        return $home > $away ? $fixture->registration1_id : $fixture->registration2_id;
    }
}
