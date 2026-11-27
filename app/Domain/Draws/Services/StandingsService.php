<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\DrawGroup;
use Illuminate\Support\Collection;

/**
 * StandingsService
 *
 * THE canonical standings calculation for the draw domain.
 * No other class should implement standings logic.
 *
 * Tiebreak cascade (in order):
 *   1. Match points (win 2, draw 1, loss 0)
 *   2. Exactly two players tied on points: head-to-head, then percentages
 *   3. Three or more tied on points: sets won %, then games won %
 *   4. Head-to-head for a remaining two-player percentage tie only
 *   5. Equal (=)
 *
 * All logic is pure / read-only.  No DB mutations.
 */
final class StandingsService
{
    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Build full standings for every RR group in the draw.
     *
     * @return array<int, array<int, array>> keyed by group_id
     */
    public function forDraw(Draw $draw): array
    {
        if ($draw->settings?->score_format === TennisScoreFormat::TIMED) {
            return app(\App\Services\InterproDrawBuilder::class)->standings($draw);
        }
        $draw->loadMissing([
            'settings',
            'groups.groupRegistrations.registration',
            'drawFixtures.fixtureResults',
        ]);

        $standings = [];
        foreach ($draw->drawFixtures as $fixture) $fixture->setRelation('draw', $draw);
        foreach ($draw->groups as $group) {
            $standings[$group->id] = $this->forGroup($group, \App\Services\Draw\HybridRoundRobinService::effectiveFixtures($draw));
        }

        return $standings;
    }

    /**
     * Build standings for a single group using the pre-loaded fixture collection.
     *
     * @param  Collection  $allFixtures  All fixtures for the draw (will be filtered to RR + group).
     * @return array<int, array>         Sorted standings rows.
     */
    public function forGroup(DrawGroup $group, Collection $allFixtures): array
    {
        if ($group->draw?->settings?->score_format === TennisScoreFormat::TIMED) {
            $draw = $group->draw;
            $draw->setRelation('drawFixtures', $allFixtures);
            return app(\App\Services\InterproDrawBuilder::class)->standings($draw)[$group->id] ?? [];
        }
        $group->loadMissing('groupRegistrations.registration');

        $withdrawn = \App\Services\Draw\RoundRobinWithdrawalService::withdrawnIds($allFixtures, (int) $group->id);
        // 1. Initialise a row per registered player
        $rows = [];
        foreach ($group->groupRegistrations as $gr) {
            $regId = $gr->registration_id;
            if ($withdrawn->contains((int) $regId)) continue;
            $rows[$regId] = [
                'reg_id'     => $regId,
                'player'     => optional($gr->registration)->display_name ?? "Player #{$regId}",
                'wins'       => 0,
                'losses'     => 0,
                'draws'      => 0,
                'points'     => 0,
                'sets_won'   => 0,
                'sets_lost'  => 0,
                'games_won'  => 0,
                'games_lost' => 0,
                'tiebreak'   => '',
            ];
        }

        // 2. Tally results from completed RR fixtures in this group
        $groupFixtures = $allFixtures->filter(
            fn($fx) => $fx->stage === 'RR' && $fx->draw_group_id === $group->id && !$fx->isRoundRobinVoided()
        );
        (new \Illuminate\Database\Eloquent\Collection($groupFixtures->all()))->loadMissing('draw.settings');

        foreach ($groupFixtures as $fx) {
            if ($fx->fixtureResults->isEmpty() && !$fx->isWalkover()) {
                continue;
            }

            $home = $fx->registration1_id;
            $away = $fx->registration2_id;

            if (! isset($rows[$home]) || ! isset($rows[$away])) {
                continue; // fixture references player not in this group's roster
            }

            [$homeSets, $awaySets, $homeGames, $awayGames] = $this->tallyFixture($fx);

            $rows[$home]['sets_won']   += $homeSets;
            $rows[$home]['sets_lost']  += $awaySets;
            $rows[$home]['games_won']  += $homeGames;
            $rows[$home]['games_lost'] += $awayGames;

            $rows[$away]['sets_won']   += $awaySets;
            $rows[$away]['sets_lost']  += $homeSets;
            $rows[$away]['games_won']  += $awayGames;
            $rows[$away]['games_lost'] += $homeGames;

            // A set winner is not a match winner; count only completed matches.
            $matchWinner = app(\App\Services\IndividualMatchOutcomeService::class)->winner($fx);
            if ($matchWinner && isset($rows[$matchWinner])) {
                $matchLoser  = ($matchWinner === $home) ? $away : $home;
                $rows[$matchWinner]['wins']++;
                $rows[$matchWinner]['points'] += 2;
                $rows[$matchLoser]['losses']++;
            } elseif (app(\App\Services\IndividualMatchOutcomeService::class)->isDraw($fx)) {
                foreach ([$home, $away] as $registration) {
                    $rows[$registration]['draws']++;
                    $rows[$registration]['points']++;
                }
            }
        }

        // 3. Sort with tiebreak cascade
        $rows = array_values($rows);
        usort($rows, $this->comparator($groupFixtures, $rows));

        // 4. Tag tiebreak indicators
        $rows = $this->tagTiebreaks($rows, $groupFixtures);

        return $rows;
    }

    /**
     * Returns the ranked registration IDs for a group (position 1, 2, 3 …).
     *
     * @return array<int, int>  [position => registration_id]
     */
    public function rankedIds(DrawGroup $group, Collection $allFixtures): array
    {
        return array_column($this->forGroup($group, $allFixtures), 'reg_id');
    }

    /**
     * Qualification mapping: returns the top-N registration IDs per group.
     *
     * @return array<int, array<int>>  keyed by group_id
     */
    public function qualifiers(Draw $draw, int $spotsPerGroup = 1): array
    {
        $standings = $this->forDraw($draw);
        $result    = [];
        foreach ($standings as $gid => $rows) {
            $result[$gid] = array_slice(array_column($rows, 'reg_id'), 0, $spotsPerGroup);
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPERS
    // ------------------------------------------------------------------

    /** Whether the first canonical standings row is separated by the configured tiebreaks. */
    public function hasUniqueLeader(Draw $draw, array $rows, Collection $fixtures, ?array $allRows = null): bool
    {
        if (! $rows) return false;
        // Keep the original points cohort when checking subsequent positions.
        // Slicing a three-way tie must not silently turn it into a new H2H-first tie.
        $compare = $this->comparator($fixtures, $allRows ?? $rows);
        foreach (array_slice($rows, 1) as $row) {
            if ($compare($rows[0], $row) >= 0) return false;
        }
        return true;
    }

    /** Tally sets and games from a single fixture's results. */
    private function tallyFixture($fx): array
    {
        $homeSets = $awaySets = $homeGames = $awayGames = 0;
        // This explicit format opts into the tournament's one-game decider rule.
        // Existing formats retain their historical totals until deliberately changed.
        $oneGameDecider = $fx->draw?->settings?->scoreFormatForStage($fx->stage)
            === TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK;
        foreach ($fx->fixtureResults as $set) {
            $s1 = (int) $set->registration1_score;
            $s2 = (int) $set->registration2_score;
            if ($oneGameDecider && (int) $set->set_nr === 3) {
                // Unfinished retirement points are not completed games. Keep the
                // original points in fixture_results for the displayed score.
                if (app(ScoreValidationService::class)->retirementSetComplete($fx, 2, $s1, $s2, $fx->fixtureResults->count())) {
                    $homeGames += (int) ($s1 > $s2);
                    $awayGames += (int) ($s2 > $s1);
                }
            } else {
                $homeGames += $s1;
                $awayGames += $s2;
            }
            if ($fx->isRetired() && !$set->winner_registration) continue;
            if ($s1 > $s2) {
                $homeSets++;
            } elseif ($s2 > $s1) {
                $awaySets++;
            }
        }
        return [$homeSets, $awaySets, $homeGames, $awayGames];
    }

    /** Build a usort comparator closure with access to the fixture collection. */
    private function comparator(Collection $fixtures, array $rows): \Closure
    {
        $headToHead = $this->headToHeadResolver($fixtures);
        return function ($a, $b) use ($headToHead, $rows) {
            $points = ($b['points'] ?? 0) <=> ($a['points'] ?? 0);
            return $points ?: $this->tieDecision($a, $b, $rows, $headToHead)[0];
        };
    }

    /** The same tie rule drives order, labels and qualification readiness. */
    private function tieDecision(array $a, array $b, array $rows, \Closure $headToHead): array
    {
        $peers = array_values(array_filter($rows, fn ($row) => ($row['points'] ?? 0) === ($a['points'] ?? 0)));
        $headToHeadDecision = static function () use ($a, $b, $headToHead): ?array {
            $winner = $headToHead((int) $a['reg_id'], (int) $b['reg_id']);
            return $winner ? [$winner === (int) $a['reg_id'] ? -1 : 1, 'H2H'] : null;
        };
        if (count($peers) === 2 && ($decision = $headToHeadDecision())) return $decision;

        $setsPct = fn ($row) => $this->percentage($row, 'sets_won', 'sets_lost');
        $gamesPct = fn ($row) => $this->percentage($row, 'games_won', 'games_lost');
        if (abs($setsPct($a) - $setsPct($b)) > 0.0001) return [$setsPct($b) <=> $setsPct($a), 'Sets %'];
        if (abs($gamesPct($a) - $gamesPct($b)) > 0.0001) return [$gamesPct($b) <=> $gamesPct($a), 'Games %'];

        $equalPercentages = array_filter($peers, fn ($row) => abs($setsPct($row) - $setsPct($a)) <= 0.0001
            && abs($gamesPct($row) - $gamesPct($a)) <= 0.0001);
        if (count($equalPercentages) === 2 && ($decision = $headToHeadDecision())) return $decision;
        // Pairwise head-to-head can form a cycle among three players. Equal
        // percentages remain unresolved, regardless of presentation order.
        return [0, '='];
    }

    private function percentage(array $row, string $won, string $lost): float
    {
        $total = ($row[$won] ?? 0) + ($row[$lost] ?? 0);
        return $total > 0 ? ($row[$won] ?? 0) / $total : 0.0;
    }

    /** Returns a closure that resolves the head-to-head winner between two players. */
    private function headToHeadResolver(Collection $fixtures): \Closure
    {
        return function (int $regA, int $regB) use ($fixtures): ?int {
            foreach ($fixtures as $fx) {
                if ($fx->stage !== 'RR') continue;
                if (
                    ($fx->registration1_id === $regA && $fx->registration2_id === $regB) ||
                    ($fx->registration1_id === $regB && $fx->registration2_id === $regA)
                ) {
                    return app(\App\Services\IndividualMatchOutcomeService::class)->winner($fx);
                }
            }
            return null;
        };
    }

    /** Tag each row with the criterion that broke its tie with the row above. */
    private function tagTiebreaks(array $rows, Collection $fixtures): array
    {
        $headToHead = $this->headToHeadResolver($fixtures);

        for ($i = 1; $i < count($rows); $i++) {
            $above = $rows[$i - 1];
            $curr  = $rows[$i];

            if ($above['points'] !== $curr['points']) continue;

            [, $label] = $this->tieDecision($above, $curr, $rows, $headToHead);
            $rows[$i]['tiebreak'] = $label;
            if (empty($rows[$i - 1]['tiebreak'])) $rows[$i - 1]['tiebreak'] = $label;
        }

        return $rows;
    }
}
