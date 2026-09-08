<?php

namespace App\Domain\Ranking\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RankingHeadToHeadEligibilityService
{
    /**
     * Apply the canonical phase rule to a fixture query that already joins
     * draws and draw_settings with the supplied aliases.
     */
    public function applyPhaseScope(
        Builder $query,
        string $fixtureAlias = 'ranking_fixtures',
        string $settingsAlias = 'ranking_draw_settings',
    ): Builder {
        return $query->where(function ($eligibleStage) use ($fixtureAlias, $settingsAlias): void {
            $eligibleStage
                ->whereNull("{$fixtureAlias}.draw_group_id")
                ->orWhere("{$settingsAlias}.workflow", 'round_robin')
                ->orWhere(function ($legacySinglePhase) use ($fixtureAlias, $settingsAlias): void {
                    $legacySinglePhase
                        ->whereNull("{$settingsAlias}.workflow")
                        ->whereNotExists(function ($playoffFixture) use ($fixtureAlias): void {
                            $playoffFixture->selectRaw('1')
                                ->from('fixtures as ranking_playoff_fixtures')
                                ->whereColumn('ranking_playoff_fixtures.draw_id', "{$fixtureAlias}.draw_id")
                                ->whereNull('ranking_playoff_fixtures.draw_group_id');
                        });
                });
        });
    }

    /**
     * Return the recorded full sets that qualify fixtures for ranking H2H.
     * A qualifying full set uses standard set scoring: 6-0..4, 7-5, or 7-6.
     * Short sets, pro sets and match tiebreaks do not qualify.
     *
     * @return Collection<int, array{set_nr:int,score:string,registration1_score:int,registration2_score:int}>
     */
    public function qualifyingFullSets(Collection $fixtureIds): Collection
    {
        if ($fixtureIds->isEmpty()) {
            return collect();
        }

        return DB::table('fixture_results')
            ->whereIn('fixture_id', $fixtureIds->unique()->values())
            ->orderBy('set_nr')
            ->orderBy('id')
            ->get(['fixture_id', 'set_nr', 'registration1_score', 'registration2_score'])
            ->filter(fn ($set) => $this->isFullSet(
                (int) $set->registration1_score,
                (int) $set->registration2_score,
            ))
            ->groupBy('fixture_id')
            ->map(function (Collection $sets): array {
                $set = $sets->first();
                $first = (int) $set->registration1_score;
                $second = (int) $set->registration2_score;

                return [
                    'set_nr' => (int) $set->set_nr,
                    'score' => $first.'-'.$second,
                    'registration1_score' => $first,
                    'registration2_score' => $second,
                ];
            });
    }

    public function isFullSet(int $firstScore, int $secondScore): bool
    {
        $high = max($firstScore, $secondScore);
        $low = min($firstScore, $secondScore);

        return ($high === 6 && $low >= 0 && $low <= 4)
            || ($high === 7 && in_array($low, [5, 6], true));
    }
}
