<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\DTO\RankingLeg;
use App\Domain\Ranking\DTO\RankingResult;
use App\Domain\Ranking\DTO\RankingRow;
use App\Models\RankingList;
use App\Models\Series;
use Illuminate\Support\Collection;

/**
 * RankingCalculationService
 *
 * Pure calculation service — no database writes.
 * Given a RankingList and its Series, produces a fully ordered,
 * tiebroken, position-assigned RankingResult.
 *
 * Pipeline:
 *  1. Load legs from placements
 *  2. Apply withdrawn-player exclusion
 *  3. Apply walkover/default exclusion (if configured)
 *  4. Apply 2-of-3 auto-award rule (if series.auto_award_rule = true)
 *  5. Apply best-N reduction
 *  6. Apply two-way tiebreak
 *  7. Assign rank positions (shared for ties)
 */
final class RankingCalculationService
{
    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Calculate rankings for one RankingList.
     *
     * @param  RankingList $list
     * @param  array       $options
     *   - bestN:         int|null     Override best-N count
     *   - excludePlayerIds: int[]     Player IDs to exclude (e.g. withdrawn)
     *   - walkoversExcluded: bool     Whether walkover wins should not count as points
     * @return RankingResult
     */
    public function calculate(RankingList $list, array $options = []): RankingResult
    {
        $series  = $list->series;
        $warnings = [];

        // Resolve best-N: list override → series default → no limit
        $bestN = (int) ($options['bestN']
            ?? $list->best_num_of_scores
            ?? $series?->best_num_of_scores
            ?? 9999);

        if ($bestN < 1) {
            throw new \InvalidArgumentException('Best-N must be at least 1.');
        }

        // Points map: position → score
        $pointsMap = $this->loadPointsMap($series);

        $duplicatePointPositions = \DB::table('points')
            ->where('series_id', $series->id)
            ->select('position')
            ->groupBy('position')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('position');
        if ($duplicatePointPositions->isNotEmpty()) {
            throw new \RuntimeException('Duplicate point mappings exist for position(s): ' . $duplicatePointPositions->implode(', '));
        }

        if ($pointsMap->isEmpty()) {
            $warnings[] = 'No points template found for this series.';
        } elseif (!$pointsMap->has(1)) {
            throw new \InvalidArgumentException('The points template must define first-place points.');
        } elseif ($pointsMap->contains(fn($score) => (int) $score < 0)) {
            throw new \InvalidArgumentException('Ranking points may not be negative.');
        }

        $orderedScores = $pointsMap->sortKeys()->values();
        for ($i = 1; $i < $orderedScores->count(); $i++) {
            if ((int) $orderedScores[$i] > (int) $orderedScores[$i - 1]) {
                throw new \InvalidArgumentException('Ranking points must not increase for a lower finishing position.');
            }
        }

        if (($series?->auto_award_rule ?? false) && !$pointsMap->has(2)) {
            throw new \InvalidArgumentException('The 2-of-3 auto-award rule requires second-place points.');
        }

        // Load raw legs
        $legs = $this->loadLegs($list, $pointsMap, $warnings);

        // Exclude withdrawn players
        $excludedPlayerIds = $options['excludePlayerIds'] ?? [];
        if (!empty($excludedPlayerIds)) {
            $before = $legs->count();
            $legs   = $legs->reject(fn(RankingLeg $l) => in_array($l->playerId, $excludedPlayerIds));
            $diff   = $before - $legs->count();
            if ($diff > 0) {
                $warnings[] = "Excluded {$diff} leg(s) for withdrawn player(s): " . implode(', ', $excludedPlayerIds);
            }
        }

        // Exclude walkover/default results (position 0 or synthetic walkover flag)
        if ($options['walkoversExcluded'] ?? false) {
            $before = $legs->count();
            $legs   = $legs->reject(fn(RankingLeg $l) => $l->position === 0);
            $diff   = $before - $legs->count();
            if ($diff > 0) {
                $warnings[] = "Excluded {$diff} walkover/default leg(s).";
            }
        }

        // Group legs by player
        /** @var array<int, RankingLeg[]> $byPlayer */
        $byPlayer = $legs->groupBy(fn(RankingLeg $l) => $l->playerId)
            ->map(fn(Collection $g) => $g->values()->all())
            ->all();

        // Apply 2-of-3 auto-award rule
        $catEventIds = $this->listCategoryEventIds($list);
        if (($series?->auto_award_rule ?? false) && $catEventIds->count() === 3) {
            [$byPlayer, $autoWarnings] = $this->applyTwoOfThreeRule(
                $byPlayer,
                $catEventIds->all(),
                $pointsMap,
                $list
            );
            $warnings = array_merge($warnings, $autoWarnings);
        }

        // Reduce to best-N and build RankingRow objects
        $rows = $this->reduceToBestN($byPlayer, $bestN, $pointsMap);

        // Two-way tiebreak
        [$rows, $tiebreakWarnings] = $this->applyTiebreak($rows, $byPlayer);
        $warnings = array_merge($warnings, $tiebreakWarnings);

        // applyTiebreak returns rows sorted points-desc with peers in tiebreak order.
        // No further re-sort is needed.

        // Assign rank positions (shared rank for equal totals only when truly unresolvable)
        $this->assignRankPositions($rows, $byPlayer);

        // Build audit trail
        $audit = $this->buildAudit($rows, $byPlayer, $catEventIds, $bestN, $pointsMap);

        return new RankingResult(
            rankingListId: $list->id,
            listName:      $list->category?->name ?? "List #{$list->id}",
            rows:          $rows,
            audit:         $audit,
            warnings:      $warnings,
        );
    }

    // ------------------------------------------------------------------
    // Step 1 — Load legs
    // ------------------------------------------------------------------

    private function loadLegs(RankingList $list, Collection $pointsMap, array &$warnings): Collection
    {
        $ceIds = $this->listCategoryEventIds($list);

        if ($ceIds->isEmpty()) {
            $warnings[] = 'No category events linked to this ranking list.';
            return collect();
        }

        $missingPositions = [];

        $rows = \DB::table('positions as p')
            ->join('category_events as ce', 'ce.id', '=', 'p.category_event_id')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->whereIn('p.category_event_id', $ceIds)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('category_event_registrations as cer')
                    ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
                    ->whereColumn('cer.category_event_id', 'p.category_event_id')
                    ->whereColumn('pr.player_id', 'p.player_id')
                    ->where(function ($withdrawn) {
                        $withdrawn->where('cer.status', 'withdrawn')
                            ->orWhereNotNull('cer.withdrawn_at');
                    });
            })
            ->select('p.player_id', 'p.category_event_id', 'p.position', 'e.start_date')
            ->get();

        $duplicates = $rows->groupBy(fn($row) => $row->player_id . ':' . $row->category_event_id)
            ->filter(fn(Collection $group) => $group->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException('Duplicate player placements exist for category event(s): ' . $duplicates->keys()->implode(', '));
        }

        $multipleWinners = $rows->where('position', 1)
            ->groupBy('category_event_id')
            ->filter(fn(Collection $group) => $group->count() > 1);
        if ($multipleWinners->isNotEmpty()) {
            throw new \RuntimeException('Multiple first-place finishers exist for category event(s): ' . $multipleWinners->keys()->implode(', '));
        }

        $legs = $rows->map(function ($r) use ($pointsMap, &$missingPositions) {
            $pos = (int) $r->position;
            if (!$pointsMap->has($pos)) {
                $missingPositions[$pos] = true;
            }
            return new RankingLeg(
                playerId:        (int) $r->player_id,
                categoryEventId: (int) $r->category_event_id,
                position:        $pos,
                points:          (int) ($pointsMap->get($pos) ?? 0),
                eventDate:       $r->start_date,
            );
        });

        if (!empty($missingPositions)) {
            $warnings[] = 'Positions without a points mapping: ' . implode(', ', array_keys($missingPositions));
        }

        return $legs;
    }

    // ------------------------------------------------------------------
    // Step 2 — 2-of-3 auto-award rule
    // ------------------------------------------------------------------

    /**
     * If a player won exactly 2 out of 3 legs and missed 1,
     * they are awarded a synthetic 1st in the missed leg,
     * and the actual winner's points for that leg are capped to 2nd-place points.
     *
     * @param  array<int, RankingLeg[]> $byPlayer
     * @param  int[]                    $ceIds
     * @param  Collection               $pointsMap
     * @param  RankingList              $list
     * @return array{array<int, RankingLeg[]>, string[]}
     */
    private function applyTwoOfThreeRule(
        array $byPlayer,
        array $ceIds,
        Collection $pointsMap,
        RankingList $list,
    ): array {
        $warnings = [];

        $firstPts = (int) ($pointsMap->get(1) ?? 0);
        $capPts   = min((int) ($pointsMap->get(2) ?? 0), $firstPts);

        // Map ceId → actual winner player_id
        $winnerByCE = [];
        foreach ($byPlayer as $pid => $legs) {
            foreach ($legs as $leg) {
                if ($leg->position === 1) {
                    $winnerByCE[$leg->categoryEventId] = (int) $pid;
                }
            }
        }

        // Which players won exactly 2 events and have an event date available
        foreach ($byPlayer as $pid => $legs) {
            $wins   = array_filter($legs, fn(RankingLeg $l) => $l->position === 1);
            $played = array_unique(array_column($legs, 'categoryEventId'));

            if (count($wins) !== 2) {
                continue;
            }

            $missed = array_values(array_diff($ceIds, $played));
            if (count($missed) !== 1) {
                continue;
            }

            $missedCeId = $missed[0];

            // Find event date for missed CE
            $refLeg   = collect($legs)->first();
            $eventDate = null;
            // Try to resolve date from existing legs or DB
            $ceDate = \DB::table('category_events as ce')
                ->join('events as e', 'e.id', '=', 'ce.event_id')
                ->where('ce.id', $missedCeId)
                ->value('e.start_date');
            $eventDate = $ceDate;

            // Inject synthetic 1st
            $byPlayer[$pid][] = new RankingLeg(
                playerId:        (int) $pid,
                categoryEventId: $missedCeId,
                position:        1,
                points:          $firstPts,
                eventDate:       $eventDate,
                synthetic:       true,
                note:            '2-of-3 clinched: auto-awarded 1st',
            );

            $warnings[] = "Player {$pid} awarded synthetic 1st in CE {$missedCeId} (2-of-3 rule).";

            // Cap actual winner for that missed event
            $actualWinner = $winnerByCE[$missedCeId] ?? null;
            if ($actualWinner !== null && $actualWinner !== (int) $pid) {
                foreach ($byPlayer[$actualWinner] as &$leg) {
                    if ($leg->categoryEventId === $missedCeId && $leg->position === 1) {
                        $leg = $leg->withPoints($capPts);
                        $warnings[] = "Player {$actualWinner} capped to {$capPts} pts in CE {$missedCeId} (displaced by 2-of-3 rule).";
                        break;
                    }
                }
                unset($leg);
            }
        }

        return [$byPlayer, $warnings];
    }

    // ------------------------------------------------------------------
    // Step 3 — Best-N reduction
    // ------------------------------------------------------------------

    /**
     * @param  array<int, RankingLeg[]> $byPlayer
     * @return Collection<RankingRow>
     */
    private function reduceToBestN(array $byPlayer, int $bestN, Collection $pointsMap): Collection
    {
        $rows = collect();

        foreach ($byPlayer as $playerId => $legs) {
            // Sort by points desc, then by date asc (earlier = preferred when equal)
            usort($legs, function (RankingLeg $a, RankingLeg $b) {
                if ($a->points !== $b->points) {
                    return $b->points <=> $a->points;
                }
                return strcmp((string) $a->eventDate, (string) $b->eventDate);
            });

            $counting = array_slice($legs, 0, $bestN);
            $dropped  = array_slice($legs, $bestN);

            $total       = array_sum(array_column($counting, 'points'));
            $wins        = count(array_filter($counting, fn(RankingLeg $l) => $l->position === 1));
            $bestSingle  = empty($counting) ? 0 : max(array_column($counting, 'points'));
            $posSum      = array_sum(array_column($counting, 'position'));
            $hasAutoAward = !empty(array_filter($counting, fn(RankingLeg $l) => $l->synthetic));

            if ($total === 0) {
                continue; // exclude players with zero total
            }

            $rows->push(new RankingRow(
                playerId:      (int) $playerId,
                rankPosition:  0,     // assigned later
                totalPoints:   $total,
                countingLegs:  $counting,
                droppedLegs:   $dropped,
                wins:          $wins,
                bestSingle:    $bestSingle,
                positionsSum:  $posSum,
                autoAward:     $hasAutoAward,
            ));
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Step 4 — Two-way tiebreak
    // ------------------------------------------------------------------

    /**
     * Tiebreak order (when totalPoints are equal):
     *  1. Most wins (position 1) in counting legs
     *  2. Highest single-leg score in counting legs
     *  3. Lowest sum of positions in counting legs
     *  4. Earliest win date
     *
     * @param  Collection<RankingRow>   $rows
     * @param  array<int, RankingLeg[]> $byPlayer
     * @return array{Collection<RankingRow>, string[]}
     */
    private function applyTiebreak(Collection $rows, array $byPlayer): array
    {
        $warnings = [];

        // Sort groups by points descending so flatMap produces the final ordering
        $sorted = $rows->groupBy(fn(RankingRow $r) => $r->totalPoints)
            ->sortKeysDesc()
            ->flatMap(function (Collection $group) use ($byPlayer, &$warnings) {
                if ($group->count() <= 1) {
                    return $group;
                }

                $pts = $group->first()->totalPoints;
                $ids = $group->pluck('playerId')->implode(', ');
                $warnings[] = "Tiebreak applied for {$group->count()} players at {$pts} pts (IDs: {$ids}).";

                $arr = $group->values()->all();
                usort($arr, function (RankingRow $a, RankingRow $b) use ($byPlayer) {
                    // 1) most wins
                    if ($a->wins !== $b->wins) {
                        return $b->wins <=> $a->wins;
                    }
                    // 2) best single
                    if ($a->bestSingle !== $b->bestSingle) {
                        return $b->bestSingle <=> $a->bestSingle;
                    }
                    // 3) lowest positions sum
                    if ($a->positionsSum !== $b->positionsSum) {
                        return $a->positionsSum <=> $b->positionsSum;
                    }
                    // 4) earliest win date
                    $aDate = $this->earliestWinDate($byPlayer[$a->playerId] ?? []);
                    $bDate = $this->earliestWinDate($byPlayer[$b->playerId] ?? []);
                    if ($aDate !== $bDate) {
                        if ($aDate === null) return 1;
                        if ($bDate === null) return -1;
                        return strcmp($aDate, $bDate);
                    }
                    return 0;
                });

                foreach ($arr as $index => $row) {
                    $peer = $arr[$index - 1] ?? $arr[$index + 1] ?? null;
                    if ($peer) {
                        $criterion = $this->tiebreakCriterion($row, $peer, $byPlayer);
                        $row->tiebreakNotes = [$criterion
                            ? "Tied on {$pts} points; ordered by {$criterion}."
                            : "Tied on {$pts} points; all configured tiebreaks remain equal."];
                    }
                }

                return collect($arr);
            })->values();

        return [$sorted, $warnings];
    }

    // ------------------------------------------------------------------
    // Step 5 — Assign rank positions
    // ------------------------------------------------------------------

    private function assignRankPositions(Collection $rows, array $byPlayer): void
    {
        $rank = 1;

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                $row->rankPosition = $rank;
                $rank++;
                continue;
            }

            /** @var RankingRow $prev */
            $prev = $rows[$i - 1];

            // Share rank only when all tiebreak criteria (including win date) are identical.
            if ($this->isTrueTie($prev, $row, $byPlayer)) {
                $row->rankPosition = $prev->rankPosition;
            } else {
                $row->rankPosition = $rank;
            }

            $rank++;
        }
    }

    /** Returns true when two rows are genuinely unresolvable by any tiebreak criterion. */
    private function isTrueTie(RankingRow $a, RankingRow $b, array $byPlayer): bool
    {
        if ($a->totalPoints !== $b->totalPoints) return false;
        if ($a->wins !== $b->wins) return false;
        if ($a->bestSingle !== $b->bestSingle) return false;
        if ($a->positionsSum !== $b->positionsSum) return false;

        $aDate = $this->earliestWinDate($byPlayer[$a->playerId] ?? []);
        $bDate = $this->earliestWinDate($byPlayer[$b->playerId] ?? []);

        return $aDate === $bDate;
    }

    private function tiebreakCriterion(RankingRow $a, RankingRow $b, array $byPlayer): ?string
    {
        if ($a->wins !== $b->wins) return 'most counting-event wins';
        if ($a->bestSingle !== $b->bestSingle) return 'highest single-event score';
        if ($a->positionsSum !== $b->positionsSum) return 'lowest sum of counting positions';

        $aDate = $this->earliestWinDate($byPlayer[$a->playerId] ?? []);
        $bDate = $this->earliestWinDate($byPlayer[$b->playerId] ?? []);
        if ($aDate !== $bDate) return 'earliest event win';

        return null;
    }

    // ------------------------------------------------------------------
    // Audit builder
    // ------------------------------------------------------------------

    private function buildAudit(
        Collection $rows,
        array $byPlayer,
        Collection $ceIds,
        int $bestN,
        Collection $pointsMap,
    ): array {
        $emptyEvents = $ceIds->filter(function (int $ceId) use ($byPlayer) {
            foreach ($byPlayer as $legs) {
                foreach ($legs as $leg) {
                    if ($leg->categoryEventId === $ceId) {
                        return false;
                    }
                }
            }
            return true;
        })->values()->all();

        return [
            'inputs' => [
                'category_event_ids' => $ceIds->values()->all(),
                'best_n'             => $bestN,
                'points_map'         => $pointsMap->all(),
                'total_players'      => count($byPlayer),
                'total_legs'         => array_sum(array_map('count', $byPlayer)),
            ],
            'empty_events'   => $emptyEvents,
            'player_details' => $rows->map(function (RankingRow $r) {
                return [
                    'player_id'      => $r->playerId,
                    'rank'           => $r->rankPosition,
                    'total_points'   => $r->totalPoints,
                    'auto_award'     => $r->autoAward,
                    'counting_legs'  => array_map(fn(RankingLeg $l) => [
                        'category_event_id' => $l->categoryEventId,
                        'position'          => $l->position,
                        'points'            => $l->points,
                        'synthetic'         => $l->synthetic,
                        'note'              => $l->note,
                    ], $r->countingLegs),
                    'dropped_legs'   => array_map(fn(RankingLeg $l) => [
                        'category_event_id' => $l->categoryEventId,
                        'position'          => $l->position,
                        'points'            => $l->points,
                    ], $r->droppedLegs),
                    'tiebreak_notes' => $r->tiebreakNotes,
                ];
            })->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function loadPointsMap(Series $series): Collection
    {
        return \DB::table('points')
            ->where('series_id', $series->id)
            ->pluck('score', 'position');
    }

    private function listCategoryEventIds(RankingList $list): Collection
    {
        return \DB::table('ranking_list_category_events')
            ->where('ranking_list_id', $list->id)
            ->pluck('category_event_id');
    }

    private function earliestWinDate(array $legs): ?string
    {
        $wins = array_filter($legs, fn(RankingLeg $l) => $l->position === 1);
        if (empty($wins)) {
            return null;
        }
        usort($wins, fn(RankingLeg $x, RankingLeg $y) => strcmp(
            (string) $x->eventDate,
            (string) $y->eventDate
        ));
        return (string) reset($wins)->eventDate;
    }
}
