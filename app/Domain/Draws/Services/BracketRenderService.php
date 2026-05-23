<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;

/**
 * BracketRenderService
 *
 * Pure read-only rendering layer for bracket draws.
 *
 * Rules:
 *   - reads DB state only; no mutations
 *   - no progression logic
 *   - no scheduling logic
 *   - no auth logic
 *   - no Blade rendering (returns plain data structures)
 *
 * Consumers (controllers, Livewire components) are responsible for
 * passing the data to the appropriate view/template.
 *
 * SvgBracketRenderer (legacy) remains operational and will be
 * gradually replaced by this service once parity is confirmed.
 */
final class BracketRenderService
{
    /** All bracket stage identifiers supported by this renderer. */
    public const ALL_STAGES = ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'];

    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Build a pure-data representation of a bracket suitable for
     * rendering by any view layer (Blade, Vue, SVG, PDF, etc.).
     *
     * Returns:
     * [
     *   'stages' => [
     *     'MAIN' => [
     *       'rounds' => [
     *         1 => [FixtureView, …],
     *         2 => [FixtureView, …],
     *       ],
     *     ],
     *     'PLATE' => …,
     *   ],
     * ]
     *
     * FixtureView keys:
     *   id, stage, round, match_nr, position,
     *   home_id, home_name, home_score,
     *   away_id, away_name, away_score,
     *   winner_id, match_status,
     *   parent_id, loser_parent_id,
     *   sets (array of {s1, s2})
     */
    public function buildBracketData(Draw $draw, array $stages = ['MAIN', 'PLATE']): array
    {
        $draw->loadMissing([
            'drawFixtures.registration1.players',
            'drawFixtures.registration2.players',
            'drawFixtures.fixtureResults',
        ]);

        $result = ['stages' => []];

        foreach ($stages as $stage) {
            $stageFixtures = $draw->drawFixtures
                ->where('stage', $stage)
                ->sortBy(fn($fx) => sprintf('%02d_%04d', $fx->round, $fx->match_nr))
                ->groupBy('round');

            if ($stageFixtures->isEmpty()) continue;

            $result['stages'][$stage] = [
                'rounds' => $stageFixtures->map(
                    fn($roundFixtures) => $roundFixtures->map(
                        fn($fx) => $this->toFixtureView($fx)
                    )->values()->all()
                )->all(),
            ];
        }

        return $result;
    }

    /**
     * Build a flat ordered list of all fixtures for an order-of-play view.
     *
     * @return array<int, array>
     */
    public function buildOopData(Draw $draw): array
    {
        $draw->loadMissing([
            'drawFixtures.registration1.players',
            'drawFixtures.registration2.players',
            'drawFixtures.fixtureResults',
            'drawFixtures.orderOfPlay.venue',
        ]);

        $stageOrder = ['RR' => 0, 'MAIN' => 1, 'PLATE' => 2, 'CONS' => 3];

        return $draw->drawFixtures
            ->sortBy(fn($fx) => sprintf(
                '%02d_%02d_%04d',
                $stageOrder[$fx->stage] ?? 9,
                $fx->round,
                $fx->match_nr
            ))
            ->map(fn($fx) => $this->toOopView($fx))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // PRIVATE TRANSFORMERS
    // ------------------------------------------------------------------

    private function toFixtureView(Fixture $fx): array
    {
        [$sets, $homeScore, $awayScore] = $this->scoreSummary($fx);

        return [
            'id'             => $fx->id,
            'stage'          => $fx->stage,
            'round'          => $fx->round,
            'match_nr'       => $fx->match_nr,
            'position'       => $fx->position,
            'home_id'        => $fx->registration1_id,
            'home_name'      => $this->playerName($fx->registration1),
            'home_score'     => $homeScore,
            'away_id'        => $fx->registration2_id,
            'away_name'      => $this->playerName($fx->registration2),
            'away_score'     => $awayScore,
            'winner_id'      => $fx->winner_registration,
            'match_status'   => $fx->match_status,
            'parent_id'      => $fx->parent_fixture_id,
            'loser_parent_id'=> $fx->loser_parent_fixture_id,
            'sets'           => $sets,
        ];
    }

    private function toOopView(Fixture $fx): array
    {
        [$sets] = $this->scoreSummary($fx);
        $oop    = $fx->relationLoaded('orderOfPlay') ? $fx->orderOfPlay : null;

        return [
            'id'         => $fx->id,
            'stage'      => $fx->stage,
            'round'      => $fx->round,
            'match_nr'   => $fx->match_nr,
            'home'       => $this->playerName($fx->registration1),
            'away'       => $this->playerName($fx->registration2),
            'home_id'    => $fx->registration1_id,
            'away_id'    => $fx->registration2_id,
            'score'      => implode(', ', array_map(fn($s) => "{$s['s1']}-{$s['s2']}", $sets)),
            'winner_id'  => $fx->winner_registration,
            'time'       => optional($oop)->start_time ?? $fx->start_time ?? '',
            'court'      => optional(optional($oop)->venue)->name ?? '',
        ];
    }

    /**
     * @return array{array, int, int}  [sets[], home_total_sets, away_total_sets]
     */
    private function scoreSummary(Fixture $fx): array
    {
        $sets       = [];
        $homeScore  = 0;
        $awayScore  = 0;

        foreach ($fx->fixtureResults->sortBy('set_nr') as $result) {
            $s1 = (int) $result->registration1_score;
            $s2 = (int) $result->registration2_score;
            $sets[] = ['s1' => $s1, 's2' => $s2];
            if ($s1 > $s2) $homeScore++;
            else            $awayScore++;
        }

        return [$sets, $homeScore, $awayScore];
    }

    private function playerName($registration): string
    {
        if (! $registration) return '';
        return optional($registration->players->first())->full_name
            ?? $registration->display_name
            ?? '';
    }
}
