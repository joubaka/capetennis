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
 *   1. Matches won
 *   2. Sets won %
 *   3. Games won %
 *   4. Head-to-head (2-player ties only)
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
        $draw->loadMissing([
            'groups.groupRegistrations.registration',
            'drawFixtures.fixtureResults',
        ]);

        $standings = [];
        foreach ($draw->groups as $group) {
            $standings[$group->id] = $this->forGroup($group, $draw->drawFixtures);
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
        $group->loadMissing('groupRegistrations.registration');

        // 1. Initialise a row per registered player
        $rows = [];
        foreach ($group->groupRegistrations as $gr) {
            $regId = $gr->registration_id;
            $rows[$regId] = [
                'reg_id'     => $regId,
                'player'     => optional($gr->registration)->display_name ?? "Player #{$regId}",
                'wins'       => 0,
                'losses'     => 0,
                'sets_won'   => 0,
                'sets_lost'  => 0,
                'games_won'  => 0,
                'games_lost' => 0,
                'tiebreak'   => '',
            ];
        }

        // 2. Tally results from completed RR fixtures in this group
        $groupFixtures = $allFixtures->filter(
            fn($fx) => $fx->stage === 'RR' && $fx->draw_group_id === $group->id
        );

        foreach ($groupFixtures as $fx) {
            if ($fx->fixtureResults->isEmpty()) {
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

            // Match win/loss from last set's declared winner_registration
            $lastSet = $fx->fixtureResults->sortBy('set_nr')->last();
            if ($lastSet && $lastSet->winner_registration) {
                $matchWinner = $lastSet->winner_registration;
                $matchLoser  = ($matchWinner === $home) ? $away : $home;
                $rows[$matchWinner]['wins']++;
                $rows[$matchLoser]['losses']++;
            }
        }

        // 3. Sort with tiebreak cascade
        $rows = array_values($rows);
        usort($rows, $this->comparator($groupFixtures));

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

    /** Tally sets and games from a single fixture's results. */
    private function tallyFixture($fx): array
    {
        $homeSets = $awaySets = $homeGames = $awayGames = 0;
        foreach ($fx->fixtureResults as $set) {
            $s1 = (int) $set->registration1_score;
            $s2 = (int) $set->registration2_score;
            $homeGames += $s1;
            $awayGames += $s2;
            if ($s1 > $s2) {
                $homeSets++;
            } else {
                $awaySets++;
            }
        }
        return [$homeSets, $awaySets, $homeGames, $awayGames];
    }

    /** Build a usort comparator closure with access to the fixture collection. */
    private function comparator(Collection $fixtures): \Closure
    {
        $headToHead = $this->headToHeadResolver($fixtures);
        $setsPct    = fn($r) => ($t = $r['sets_won'] + $r['sets_lost'])  > 0 ? $r['sets_won']  / $t : 0.0;
        $gamesPct   = fn($r) => ($t = $r['games_won'] + $r['games_lost']) > 0 ? $r['games_won'] / $t : 0.0;

        return function ($a, $b) use ($headToHead, $setsPct, $gamesPct) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            $aSP = $setsPct($a); $bSP = $setsPct($b);
            if (abs($aSP - $bSP) > 0.0001) return $bSP <=> $aSP;

            $aGP = $gamesPct($a); $bGP = $gamesPct($b);
            if (abs($aGP - $bGP) > 0.0001) return $bGP <=> $aGP;

            $hh = $headToHead($a['reg_id'], $b['reg_id']);
            if ($hh) return $hh === $a['reg_id'] ? -1 : 1;

            return 0;
        };
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
                    return $fx->winner_registration ?: null;
                }
            }
            return null;
        };
    }

    /** Tag each row with the criterion that broke its tie with the row above. */
    private function tagTiebreaks(array $rows, Collection $fixtures): array
    {
        $headToHead = $this->headToHeadResolver($fixtures);
        $setsPct    = fn($r) => ($t = $r['sets_won'] + $r['sets_lost'])  > 0 ? $r['sets_won']  / $t : 0.0;
        $gamesPct   = fn($r) => ($t = $r['games_won'] + $r['games_lost']) > 0 ? $r['games_won'] / $t : 0.0;

        for ($i = 1; $i < count($rows); $i++) {
            $above = $rows[$i - 1];
            $curr  = $rows[$i];

            if ($above['wins'] !== $curr['wins']) continue;

            if (abs($setsPct($above) - $setsPct($curr)) > 0.0001) {
                $rows[$i]['tiebreak']       = 'Sets %';
                if (empty($rows[$i - 1]['tiebreak'])) $rows[$i - 1]['tiebreak'] = 'Sets %';
                continue;
            }

            if (abs($gamesPct($above) - $gamesPct($curr)) > 0.0001) {
                $rows[$i]['tiebreak']       = 'Games %';
                if (empty($rows[$i - 1]['tiebreak'])) $rows[$i - 1]['tiebreak'] = 'Games %';
                continue;
            }

            $hh = $headToHead($above['reg_id'], $curr['reg_id']);
            if ($hh) {
                $rows[$i]['tiebreak']       = 'H2H';
                if (empty($rows[$i - 1]['tiebreak'])) $rows[$i - 1]['tiebreak'] = 'H2H';
            } else {
                $rows[$i]['tiebreak']       = '=';
                if (empty($rows[$i - 1]['tiebreak'])) $rows[$i - 1]['tiebreak'] = '=';
            }
        }

        return $rows;
    }
}
