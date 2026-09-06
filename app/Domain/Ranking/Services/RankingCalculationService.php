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
 *  6. Apply dropped-score, then latest head-to-head tiebreak
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

        // Series-configured tiebreaks, applied in their fixed displayed order.
        $useThirdScoreTiebreak = (bool) ($series?->use_third_score_tiebreak ?? true);
        $useHeadToHeadTiebreak = (bool) ($series?->use_head_to_head_tiebreak ?? true);
        $headToHeadWinners = $useHeadToHeadTiebreak
            ? $this->latestHeadToHeadWinners($list, $rows->pluck('playerId'))
            : [];
        [$rows, $tiebreakWarnings, $appliedHeadToHeadPairs] = $this->applyTiebreak(
            $rows,
            $headToHeadWinners,
            $useThirdScoreTiebreak
        );
        $warnings = array_merge($warnings, $tiebreakWarnings);

        // applyTiebreak returns rows sorted points-desc with peers in tiebreak order.
        // No further re-sort is needed.

        // Assign rank positions (shared rank for equal totals only when truly unresolvable)
        $this->assignRankPositions($rows, $appliedHeadToHeadPairs, $useThirdScoreTiebreak);

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
        $maxMappedPosition = (int) ($pointsMap->keys()->max() ?? 0);

        $rows = \DB::table('category_results as cr')
            ->join('category_events as ce', function ($join) {
                $join->on('ce.event_id', '=', 'cr.event_id')
                    ->on('ce.category_id', '=', 'cr.category_id');
            })
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->join('player_registrations as pr', 'pr.registration_id', '=', 'cr.registration_id')
            ->whereIn('ce.id', $ceIds)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('category_event_registrations as cer')
                    ->whereColumn('cer.category_event_id', 'ce.id')
                    ->whereColumn('cer.registration_id', 'cr.registration_id')
                    ->where(function ($withdrawn) {
                        $withdrawn->whereIn('cer.status', [
                            'withdrawn',
                            'withdrawn_pending_refund',
                            'withdrawn_refunded',
                        ])
                            ->orWhereNotNull('cer.withdrawn_at');
                    });
            })
            ->select(
                'pr.player_id',
                'ce.id as category_event_id',
                'cr.position',
                'e.start_date'
            )
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

        $legs = $rows->map(function ($r) use ($pointsMap, $maxMappedPosition, &$missingPositions) {
            $pos = (int) $r->position;
            // Positions below the configured points-paying range legitimately
            // earn zero. A gap inside that range is a broken points template.
            if ($pos > 0 && $pos <= $maxMappedPosition && !$pointsMap->has($pos)) {
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
            throw new \RuntimeException(
                'Positions without a points mapping: ' . implode(', ', array_keys($missingPositions))
            );
        }

        return $legs;
    }

    // ------------------------------------------------------------------
    // Step 2 — 2-of-3 auto-award rule
    // ------------------------------------------------------------------

    /**
     * If a player won exactly 2 out of 3 legs and missed 1,
     * they are awarded a synthetic 1st in the missed leg. Every actual
     * finisher in that leg is shifted down one position and receives the
     * points for the shifted position.
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

            $shifted = 0;
            foreach ($byPlayer as &$playerLegs) {
                foreach ($playerLegs as &$leg) {
                    if ($leg->categoryEventId !== $missedCeId || $leg->synthetic) {
                        continue;
                    }

                    $shiftedPosition = $leg->position + 1;
                    $shiftedPoints = (int) ($pointsMap->get($shiftedPosition) ?? 0);
                    $leg = $leg->withPositionAndPoints($shiftedPosition, $shiftedPoints);
                    $shifted++;
                }
                unset($leg);
            }
            unset($playerLegs);

            $warnings[] = "Shifted {$shifted} finisher(s) down one position in CE {$missedCeId} after the 2-of-3 automatic award.";
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
            $hasAutoAward = !empty(array_filter($legs, fn(RankingLeg $l) => $l->synthetic));

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
                eventsPlayed:  count($legs),
                bestSingle:    $bestSingle,
                positionsSum:  $posSum,
                autoAward:     $hasAutoAward,
            ));
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Step 4 — Tiebreak
    // ------------------------------------------------------------------

    /**
     * Tiebreak order (when best-N totalPoints are equal):
     *  1. Highest score outside the best-N total (zero when there is none)
     *  2. Winner of the most recent recorded head-to-head match
     *  3. Shared rank when neither rule resolves the tie
     *
     * @param  Collection<RankingRow>   $rows
     * @return array{Collection<RankingRow>, string[], array<string, array<string, mixed>>}
     */
    private function applyTiebreak(Collection $rows, array $headToHeadWinners, bool $useThirdScoreTiebreak): array
    {
        $warnings = [];
        $appliedHeadToHeadPairs = [];

        // Sort groups by points descending so flatMap produces the final ordering
        $sorted = $rows->groupBy(fn(RankingRow $r) => $r->totalPoints)
            ->sortKeysDesc()
            ->flatMap(function (Collection $group) use ($headToHeadWinners, $useThirdScoreTiebreak, &$warnings, &$appliedHeadToHeadPairs) {
                if ($group->count() <= 1) {
                    return $group;
                }

                $pts = $group->first()->totalPoints;
                $ids = $group->pluck('playerId')->implode(', ');
                $warnings[] = "Tiebreak applied for {$group->count()} players at {$pts} pts (IDs: {$ids}).";

                return $group
                    ->groupBy(fn (RankingRow $row) => $useThirdScoreTiebreak ? $this->nextBestScore($row) : 0)
                    ->sortKeysDesc()
                    ->flatMap(function (Collection $thirdScoreGroup) use ($group, $headToHeadWinners, $pts, $useThirdScoreTiebreak, &$appliedHeadToHeadPairs) {
                        $rows = $thirdScoreGroup->values()->all();
                        $thirdScore = $this->nextBestScore($rows[0]);

                        if ($thirdScoreGroup->count() === 2) {
                            $pairKey = $this->playerPairKey($rows[0]->playerId, $rows[1]->playerId);
                            if (isset($headToHeadWinners[$pairKey])) {
                                $appliedHeadToHeadPairs[$pairKey] = $headToHeadWinners[$pairKey];
                            }
                            usort($rows, fn (RankingRow $a, RankingRow $b) => $this->compareTiedRows(
                                $a,
                                $b,
                                $headToHeadWinners,
                                $useThirdScoreTiebreak
                            ));
                        }

                        foreach ($rows as $row) {
                            if ($useThirdScoreTiebreak && $thirdScoreGroup->count() === 1) {
                                $row->tiebreakNotes = ["Tied on {$pts} points; compared by third-event score ({$thirdScore} points)."];
                                continue;
                            }

                            $peer = collect($rows)->first(fn (RankingRow $candidate) => $candidate->playerId !== $row->playerId);
                            $criterion = $peer ? $this->tiebreakCriterion(
                                $row,
                                $peer,
                                $headToHeadWinners,
                                $useThirdScoreTiebreak
                            ) : null;
                            $row->tiebreakNotes = [$criterion
                                ? "Tied on {$pts} points and third-event score; ordered by {$criterion}."
                                : "Tied on {$pts} points; no enabled tiebreak rule resolved the tie."];
                        }

                        return collect($rows);
                    });
            })->values();

        return [$sorted, $warnings, $appliedHeadToHeadPairs];
    }

    // ------------------------------------------------------------------
    // Step 5 — Assign rank positions
    // ------------------------------------------------------------------

    private function assignRankPositions(
        Collection $rows,
        array $appliedHeadToHeadPairs,
        bool $useThirdScoreTiebreak
    ): void
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

            if ($this->isTrueTie($prev, $row, $appliedHeadToHeadPairs, $useThirdScoreTiebreak)) {
                $row->rankPosition = $prev->rankPosition;
            } else {
                $row->rankPosition = $rank;
            }

            $rank++;
        }
    }

    /** Returns true when two rows are genuinely unresolvable by any tiebreak criterion. */
    private function isTrueTie(
        RankingRow $a,
        RankingRow $b,
        array $headToHeadWinners,
        bool $useThirdScoreTiebreak
    ): bool
    {
        if ($a->totalPoints !== $b->totalPoints) return false;
        if ($useThirdScoreTiebreak && $this->nextBestScore($a) !== $this->nextBestScore($b)) return false;

        return ! isset($headToHeadWinners[$this->playerPairKey($a->playerId, $b->playerId)]);
    }

    private function tiebreakCriterion(
        RankingRow $a,
        RankingRow $b,
        array $headToHeadWinners,
        bool $useThirdScoreTiebreak
    ): ?string
    {
        if ($useThirdScoreTiebreak && $this->nextBestScore($a) !== $this->nextBestScore($b)) {
            return 'higher third-event score';
        }

        $headToHead = $headToHeadWinners[$this->playerPairKey($a->playerId, $b->playerId)] ?? null;
        if ($headToHead) {
            return 'latest head-to-head winner ('.$headToHead['event_name'].')';
        }

        return null;
    }

    private function compareTiedRows(
        RankingRow $a,
        RankingRow $b,
        array $headToHeadWinners,
        bool $useThirdScoreTiebreak
    ): int
    {
        if ($useThirdScoreTiebreak) {
            $thirdScoreComparison = $this->nextBestScore($b) <=> $this->nextBestScore($a);
            if ($thirdScoreComparison !== 0) {
                return $thirdScoreComparison;
            }
        }

        $headToHead = $headToHeadWinners[$this->playerPairKey($a->playerId, $b->playerId)] ?? null;
        if (! $headToHead) {
            return 0;
        }

        return $headToHead['winner_player_id'] === $a->playerId ? -1 : 1;
    }

    private function nextBestScore(RankingRow $row): int
    {
        return empty($row->droppedLegs) ? 0 : max(array_column($row->droppedLegs, 'points'));
    }

    /**
     * Resolve one winner per player pair from the latest recorded match in the
     * category events linked to this ranking list.
     *
     * @return array<string, array{winner_player_id:int,event_id:int,event_name:string,fixture_id:int}>
     */
    private function latestHeadToHeadWinners(RankingList $list, Collection $playerIds): array
    {
        if ($playerIds->count() < 2) {
            return [];
        }

        $categoryEventIds = $this->listCategoryEventIds($list);
        $fixtures = \DB::table('fixtures as ranking_fixtures')
            ->join('draws as ranking_draws', 'ranking_draws.id', '=', 'ranking_fixtures.draw_id')
            ->leftJoin('draw_settings as ranking_draw_settings', 'ranking_draw_settings.draw_id', '=', 'ranking_draws.id')
            ->join('events as ranking_events', 'ranking_events.id', '=', 'ranking_draws.event_id')
            ->join('player_registrations as ranking_pr1', 'ranking_pr1.registration_id', '=', 'ranking_fixtures.registration1_id')
            ->join('player_registrations as ranking_pr2', 'ranking_pr2.registration_id', '=', 'ranking_fixtures.registration2_id')
            ->where(function ($query) use ($categoryEventIds) {
                $query->whereIn('ranking_draws.category_event_id', $categoryEventIds)
                    ->orWhereExists(function ($membership) use ($categoryEventIds) {
                        $membership->selectRaw('1')
                            ->from('category_event_registrations as ranking_cer1')
                            ->join('category_event_registrations as ranking_cer2', 'ranking_cer2.category_event_id', '=', 'ranking_cer1.category_event_id')
                            ->join('category_events as ranking_member_ce', 'ranking_member_ce.id', '=', 'ranking_cer1.category_event_id')
                            ->whereColumn('ranking_cer1.registration_id', 'ranking_fixtures.registration1_id')
                            ->whereColumn('ranking_cer2.registration_id', 'ranking_fixtures.registration2_id')
                            ->whereColumn('ranking_member_ce.event_id', 'ranking_draws.event_id')
                            ->whereIn('ranking_member_ce.id', $categoryEventIds);
                    });
            })
            ->whereIn('ranking_pr1.player_id', $playerIds)
            ->whereIn('ranking_pr2.player_id', $playerIds)
            ->where(function ($eligibleStage) {
                $eligibleStage
                    // A fixture outside a round-robin group is a playoff/bracket match.
                    ->whereNull('ranking_fixtures.draw_group_id')
                    // Group matches count only when round robin is the draw's sole phase.
                    ->orWhere('ranking_draw_settings.workflow', 'round_robin')
                    // Historical draws may predate workflow settings. Treat them as
                    // single-phase only when the draw contains no playoff fixtures.
                    ->orWhere(function ($legacySinglePhase) {
                        $legacySinglePhase
                            ->whereNull('ranking_draw_settings.workflow')
                            ->whereNotExists(function ($playoffFixture) {
                                $playoffFixture->selectRaw('1')
                                    ->from('fixtures as ranking_playoff_fixtures')
                                    ->whereColumn('ranking_playoff_fixtures.draw_id', 'ranking_fixtures.draw_id')
                                    ->whereNull('ranking_playoff_fixtures.draw_group_id');
                            });
                    });
            })
            ->orderByDesc('ranking_events.start_date')
            ->orderByDesc('ranking_fixtures.id')
            ->get([
                'ranking_fixtures.id',
                'ranking_fixtures.registration1_id',
                'ranking_fixtures.registration2_id',
                'ranking_fixtures.winner_registration',
                'ranking_pr1.player_id as player1_id',
                'ranking_pr2.player_id as player2_id',
                'ranking_events.id as event_id',
                'ranking_events.name as event_name',
            ]);
        $resultWinners = \DB::table('fixture_results')
            ->whereIn('fixture_id', $fixtures->pluck('id'))
            ->whereNotNull('winner_registration')
            ->orderByDesc('set_nr')
            ->orderByDesc('id')
            ->get(['fixture_id', 'winner_registration'])
            ->groupBy('fixture_id')
            ->map(fn (Collection $results) => (int) $results->first()->winner_registration);
        $winners = [];

        foreach ($fixtures as $fixture) {
            $pairKey = $this->playerPairKey((int) $fixture->player1_id, (int) $fixture->player2_id);
            if (isset($winners[$pairKey])) {
                continue;
            }

            $winnerRegistration = (int) ($fixture->winner_registration ?: $resultWinners->get($fixture->id, 0));
            if ($winnerRegistration === (int) $fixture->registration1_id) {
                $winnerPlayerId = (int) $fixture->player1_id;
            } elseif ($winnerRegistration === (int) $fixture->registration2_id) {
                $winnerPlayerId = (int) $fixture->player2_id;
            } else {
                continue;
            }

            $winners[$pairKey] = [
                'winner_player_id' => $winnerPlayerId,
                'event_id' => (int) $fixture->event_id,
                'event_name' => (string) $fixture->event_name,
                'fixture_id' => (int) $fixture->id,
            ];
        }

        return $winners;
    }

    private function playerPairKey(int $playerA, int $playerB): string
    {
        $ids = [$playerA, $playerB];
        sort($ids);

        return implode(':', $ids);
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

}
