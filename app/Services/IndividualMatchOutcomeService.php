<?php

namespace App\Services;

use App\Domain\Draws\Services\{ScoreValidationService, TennisScoreFormat};
use App\Models\Fixture;

final class IndividualMatchOutcomeService
{
    public function winner(Fixture $fixture): ?int
    {
        $sets = $fixture->fixtureResults->sortBy('set_nr')->unique('set_nr')
            ->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all();
        return $this->winnerFromSets($fixture, $sets);
    }

    public function winnerFromSets(Fixture $fixture, array $sets): ?int
    {
        if (!app(ScoreValidationService::class)->validate($fixture, $sets)['valid']) return null;
        $settings = $fixture->draw?->settings;
        $format = (string) ($settings?->score_format ?? '');
        $needed = in_array($format, TennisScoreFormat::keys(), true) ? TennisScoreFormat::get($format)['wins_needed']
            : (int) ceil(max(1, min(5, (int) ($settings?->num_sets ?: 3))) / 2);
        $home = count(array_filter($sets, fn ($set) => $set[0] > $set[1]));
        $away = count($sets) - $home;
        if (max($home, $away) < $needed || $home === $away) return null;
        return $home > $away ? $fixture->registration1_id : $fixture->registration2_id;
    }
}
